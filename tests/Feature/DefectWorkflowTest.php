<?php

use App\Enums\DefectStatus;
use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\DefectPhoto;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('defect_photos');

    foreach ([
        'manager' => UserRole::Manager,
        'owner' => UserRole::Owner,
        'outsider' => UserRole::Owner,
        'contractor' => UserRole::Contractor,
        'otherContractor' => UserRole::Contractor,
    ] as $name => $role) {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        $this->{$name} = $user;
    }

    $property = new Property([
        'name' => 'Workflow Test House',
        'address' => '123 Example Road',
    ]);
    $property->creator()->associate($this->manager);
    $property->save();
    $property->members()->attach($this->owner->id);

    $room = $property->rooms()->create(['name' => 'Bathroom']);

    $this->defect = new Defect([
        'title' => 'Leaking tap',
        'description' => 'The tap keeps leaking.',
        'category' => 'plumbing',
    ]);
    $this->defect->property()->associate($property);
    $this->defect->room()->associate($room);
    $this->defect->reporter()->associate($this->owner);
    $this->defect->assignee()->associate($this->contractor);
    $this->defect->status = DefectStatus::Assigned;
    $this->defect->priority = 'medium';
    $this->defect->save();

    $this->url = '/defects/'.$this->defect->id;
});

test('guests must log in for every workflow action', function () {
    foreach (['assignment', 'start', 'verify', 'reopen'] as $action) {
        $this->patch($this->url.'/'.$action)
            ->assertRedirect(route('login'));
    }

    $this->post($this->url.'/repair')
        ->assertRedirect(route('login'));

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Assigned);
});

test('manager can assign a contractor with priority and due date', function () {
    $this->defect->status = DefectStatus::Reported;
    $this->defect->assigned_to = null;
    $this->defect->save();

    $due = now()->addDays(3)->format('Y-m-d');

    $this->actingAs($this->manager)
        ->patch($this->url.'/assignment', [
            'assigned_to' => $this->contractor->id,
            'priority' => 'high',
            'due_date' => $due,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $defect = $this->defect->fresh();

    expect($defect->status)->toBe(DefectStatus::Assigned)
        ->and($defect->assigned_to)->toBe($this->contractor->id)
        ->and($defect->priority)->toBe('high')
        ->and($defect->due_date->format('Y-m-d'))->toBe($due);
});

test('assignment rejects invalid contractor priority and past date', function () {
    $this->actingAs($this->manager)
        ->patch($this->url.'/assignment', [
            'assigned_to' => $this->owner->id,
            'priority' => 'invalid',
            'due_date' => now()->subDay()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors([
            'assigned_to',
            'priority',
            'due_date',
        ]);

    expect($this->defect->fresh()->assigned_to)
        ->toBe($this->contractor->id);
});

test('non-managers cannot assign contractors', function (string $actor) {
    $this->actingAs($this->{$actor})
        ->patch($this->url.'/assignment', [
            'assigned_to' => $this->otherContractor->id,
            'priority' => 'high',
            'due_date' => now()->addDay()->format('Y-m-d'),
        ])
        ->assertForbidden();

    expect($this->defect->fresh()->assigned_to)
        ->toBe($this->contractor->id);
})->with(['owner', 'outsider', 'contractor']);

test('only the assigned contractor can start and submit repairs', function (
    string $actor,
) {
    $this->actingAs($this->{$actor})
        ->patch($this->url.'/start')
        ->assertForbidden();

    $this->defect->status = DefectStatus::InProgress;
    $this->defect->save();

    $this->post($this->url.'/repair', [
        'repair_notes' => 'Attempted repair.',
        'photos' => [UploadedFile::fake()->image('after.jpg')],
    ])->assertForbidden();

    $this->assertDatabaseCount('defect_photos', 0);
    expect(Storage::disk('defect_photos')->allFiles())->toBeEmpty();
})->with(['manager', 'owner', 'outsider', 'otherContractor']);

test('contractor can start work and submit repair evidence', function () {
    $this->actingAs($this->contractor)
        ->patch($this->url.'/start')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->defect->fresh()->status)->toBe(DefectStatus::InProgress)
        ->and($this->defect->fresh()->started_at)->not->toBeNull();

    $this->get($this->url)
        ->assertOk()
        ->assertSee('Submit completed repair');

    $this->post($this->url.'/repair', [
        'repair_notes' => 'Replaced the washer and tested the tap.',
        'photos' => [
            2 => UploadedFile::fake()->image('after-one.jpg'),
            5 => UploadedFile::fake()->image('after-two.png'),
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $defect = $this->defect->fresh();

    expect($defect->status)->toBe(DefectStatus::Repaired)
        ->and($defect->repair_notes)
        ->toBe('Replaced the washer and tested the tap.')
        ->and($defect->repaired_at)->not->toBeNull();

    $this->assertDatabaseCount('defect_photos', 2);

    foreach (DefectPhoto::query()->get() as $photo) {
        expect($photo->type)->toBe('after')
            ->and($photo->uploaded_by)->toBe($this->contractor->id)
            ->and($photo->defect_id)->toBe($defect->id);

        Storage::disk('defect_photos')->assertExists($photo->path);
    }
});

test('repair requires notes and at least one photo', function () {
    $this->defect->status = DefectStatus::InProgress;
    $this->defect->save();

    $this->actingAs($this->contractor)
        ->post($this->url.'/repair', [])
        ->assertSessionHasErrors(['repair_notes', 'photos']);

    expect($this->defect->fresh()->status)->toBe(DefectStatus::InProgress);
    $this->assertDatabaseCount('defect_photos', 0);
});

test('repair rejects invalid photo uploads', function (
    string $kind,
    string $error,
) {
    $this->defect->status = DefectStatus::InProgress;
    $this->defect->save();

    $photos = match ($kind) {
        'document' => [
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ],
        'large' => [
            UploadedFile::fake()->image('large.jpg')->size(2049),
        ],
        'wide' => [
            UploadedFile::fake()->image('wide.jpg', 6001, 10),
        ],
        'many' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
            UploadedFile::fake()->image('four.jpg'),
        ],
    };

    $this->actingAs($this->contractor)
        ->post($this->url.'/repair', [
            'repair_notes' => 'Repair completed.',
            'photos' => $photos,
        ])
        ->assertSessionHasErrors($error);

    expect($this->defect->fresh()->status)->toBe(DefectStatus::InProgress);
    $this->assertDatabaseCount('defect_photos', 0);
    expect(Storage::disk('defect_photos')->allFiles())->toBeEmpty();
})->with([
    ['document', 'photos.0'],
    ['large', 'photos.0'],
    ['wide', 'photos.0'],
    ['many', 'photos'],
]);

test('repairs cannot skip the start work step', function () {
    $this->actingAs($this->contractor)
        ->post($this->url.'/repair', [
            'repair_notes' => 'Repair completed.',
            'photos' => [UploadedFile::fake()->image('after.jpg')],
        ])
        ->assertForbidden();

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Assigned);
    $this->assertDatabaseCount('defect_photos', 0);
});

test('work cannot restart or be reassigned while in progress', function () {
    $this->defect->status = DefectStatus::InProgress;
    $this->defect->save();

    $this->actingAs($this->contractor)
        ->patch($this->url.'/start')
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->patch($this->url.'/assignment', [
            'assigned_to' => $this->otherContractor->id,
            'priority' => 'high',
            'due_date' => now()->addDay()->format('Y-m-d'),
        ])
        ->assertForbidden();

    expect($this->defect->fresh()->assigned_to)
        ->toBe($this->contractor->id);
});

test('managers can verify reopen and restart the cycle', function (
    string $actor,
) {
    $this->defect->status = DefectStatus::Repaired;
    $this->defect->repair_notes = 'Washer replaced.';
    $this->defect->repaired_at = now();
    $this->defect->save();

    $this->actingAs($this->{$actor})
        ->get($this->url)
        ->assertOk()
        ->assertSee('Verify repair');

    $this->patch($this->url.'/verify')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $defect = $this->defect->fresh();

    expect($defect->status)->toBe(DefectStatus::Verified)
        ->and($defect->reviewed_by)->toBe($this->{$actor}->id)
        ->and($defect->verified_at)->not->toBeNull();

    $this->patch($this->url.'/reopen', [
        'reopen_reason' => 'The tap is leaking again.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $defect = $this->defect->fresh();

    expect($defect->status)->toBe(DefectStatus::Reopened)
        ->and($defect->verified_at)->toBeNull()
        ->and($defect->reopened_at)->not->toBeNull()
        ->and($defect->reopen_reason)->toBe('The tap is leaking again.')
        ->and($defect->repair_notes)->toBe('Washer replaced.')
        ->and($defect->assigned_to)->toBe($this->contractor->id);

    $this->actingAs($this->contractor)
        ->patch($this->url.'/start')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->defect->fresh()->status)->toBe(DefectStatus::InProgress);
})->with(['manager']);

test('contractors and owners cannot review repairs', function (string $actor,
) {
    $this->defect->status = DefectStatus::Repaired;
    $this->defect->save();

    $this->actingAs($this->{$actor})
        ->patch($this->url.'/verify')
        ->assertForbidden();

    $this->patch($this->url.'/reopen', [
        'reopen_reason' => 'Attempted reopening.',
    ])->assertForbidden();

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Repaired);
})->with(['contractor', 'otherContractor', 'outsider', 'owner']);

test('reopening requires a reason', function () {
    $this->defect->status = DefectStatus::Repaired;
    $this->defect->save();

    $this->actingAs($this->manager)
        ->patch($this->url.'/reopen', ['reopen_reason' => ''])
        ->assertSessionHasErrors('reopen_reason');

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Repaired);
});

test('unfinished defects cannot be verified or reopened', function () {
    $this->actingAs($this->manager)
        ->patch($this->url.'/verify')
        ->assertForbidden();

    $this->patch($this->url.'/reopen', [
        'reopen_reason' => 'Not repaired yet.',
    ])->assertForbidden();

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Assigned);
});

test('linked owners can request review through comments but cannot reopen verified repairs', function () {
    $this->defect->status = DefectStatus::Verified;
    $this->defect->verified_at = now();
    $this->defect->reviewed_by = $this->manager->id;
    $this->defect->save();

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertOk()
        ->assertDontSee(
            'action="'.route('defects.verify', $this->defect).'"',
            false,
        )
        ->assertDontSee(
            'action="'.route('defects.reopen', $this->defect).'"',
            false,
        );

    $this->patch($this->url.'/reopen', [
        'reopen_reason' => 'The tap is leaking again.',
    ])->assertForbidden();

    $this->post($this->url.'/comments', [
        'body' => 'Please review the tap again; it is still leaking.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('defect_comments', [
        'defect_id' => $this->defect->id,
        'user_id' => $this->owner->id,
        'body' => 'Please review the tap again; it is still leaking.',
    ]);

    expect($this->defect->fresh()->status)->toBe(DefectStatus::Verified)
        ->and($this->defect->fresh()->reviewed_by)->toBe($this->manager->id);
});

test('inactive contractors are excluded from assignment options', function () {
    $this->otherContractor->is_active = false;
    $this->otherContractor->save();

    $this->actingAs($this->manager)
        ->get(route('defects.show', $this->defect))
        ->assertOk()
        ->assertViewHas('contractors', function ($contractors): bool {
            return $contractors->contains('id', $this->contractor->id)
                && ! $contractors->contains('id', $this->otherContractor->id);
        });
});

test('assignment to an inactive contractor is rejected without changing history', function () {
    $this->otherContractor->is_active = false;
    $this->otherContractor->save();

    $activityCount = $this->defect->activities()->count();

    $this->actingAs($this->manager)
        ->patch($this->url.'/assignment', [
            'assigned_to' => $this->otherContractor->id,
            'priority' => 'high',
            'due_date' => now()->addDays(3)->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('assigned_to');

    $defect = $this->defect->fresh();

    expect($defect->assigned_to)->toBe($this->contractor->id)
        ->and($defect->status)->toBe(DefectStatus::Assigned)
        ->and($defect->priority)->toBe('medium')
        ->and($defect->activities()->count())->toBe($activityCount);
});

test('deactivation preserves assigned defects and their activity history', function () {
    $this->defect->recordActivity(
        $this->contractor,
        'test_record',
        'Historical contractor activity.',
    );

    $activityCount = $this->defect->activities()->count();

    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->contractor), [
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $defect = $this->defect->fresh();

    expect($defect->assigned_to)->toBe($this->contractor->id)
        ->and($defect->assignee->name)->toBe($this->contractor->name)
        ->and($defect->status)->toBe(DefectStatus::Assigned)
        ->and($defect->activities()->count())->toBe($activityCount);

    $this->get(route('defects.show', $defect))
        ->assertOk()
        ->assertSee('Historical contractor activity.');
});
