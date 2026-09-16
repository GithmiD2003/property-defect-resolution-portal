<?php

use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\DefectActivity;
use App\Models\DefectComment;
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

    $this->property = new Property([
        'name' => 'Activity Test House',
        'address' => '123 Example Road',
    ]);
    $this->property->creator()->associate($this->manager);
    $this->property->save();
    $this->property->members()->attach($this->owner->id);

    $room = $this->property->rooms()->create(['name' => 'Kitchen']);

    $this->actingAs($this->manager)
        ->post('/properties/'.$this->property->id.'/defects', [
            'title' => 'Broken kitchen tile',
            'description' => 'The tile beside the sink is cracked.',
            'category' => 'tiling',
            'room_id' => $room->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->defect = Defect::query()->sole();
    $this->url = '/defects/'.$this->defect->id;

    $this->patch($this->url.'/assignment', [
        'assigned_to' => $this->contractor->id,
        'priority' => 'high',
        'due_date' => now()->addDays(3)->format('Y-m-d'),
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('reporting and assignment record the actor and details', function () {
    $reported = DefectActivity::query()
        ->where('event', 'reported')
        ->sole();

    $assigned = DefectActivity::query()
        ->where('event', 'assigned')
        ->sole();

    expect($reported->defect_id)->toBe($this->defect->id)
        ->and($reported->user_id)->toBe($this->manager->id)
        ->and($reported->metadata['title'])->toBe('Broken kitchen tile')
        ->and($assigned->user_id)->toBe($this->manager->id)
        ->and($assigned->metadata['assigned_to'])->toBe($this->contractor->id)
        ->and($assigned->metadata['priority'])->toBe('high');

    $this->get($this->url)
        ->assertOk()
        ->assertSee('Activity history')
        ->assertSee('Defect reported.')
        ->assertSee('Assignment saved for');
});

test('authorized users can comment without impersonating another user', function (
    string $actor,
) {
    $this->actingAs($this->{$actor})
        ->post($this->url.'/comments', [
            'body' => 'Please check the tile near the sink.',
            'user_id' => $this->outsider->id,
            'defect_id' => 999999,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $comment = DefectComment::query()->sole();

    expect($comment->user_id)->toBe($this->{$actor}->id)
        ->and($comment->defect_id)->toBe($this->defect->id)
        ->and($comment->body)->toBe('Please check the tile near the sink.');

    $this->get($this->url)
        ->assertOk()
        ->assertSee('Please check the tile near the sink.');
})->with(['manager', 'owner', 'contractor']);

test('unauthorized users cannot read history or post comments', function (
    string $actor,
) {
    $this->actingAs($this->{$actor})
        ->get($this->url)
        ->assertForbidden();

    $this->post($this->url.'/comments', [
        'body' => 'Unauthorized comment.',
    ])->assertForbidden();

    $this->assertDatabaseCount('defect_comments', 0);
})->with(['outsider', 'otherContractor']);

test('guests must log in before commenting', function () {
    $this->app['auth']->forgetGuards();

    $this->post($this->url.'/comments', [
        'body' => 'Guest comment.',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('defect_comments', 0);
});

test('comments require nonblank text within the length limit', function () {
    $this->actingAs($this->owner);

    foreach (['', '   ', str_repeat('a', 5001)] as $body) {
        $this->post($this->url.'/comments', [
            'body' => $body,
        ])->assertSessionHasErrors('body');
    }

    $this->assertDatabaseCount('defect_comments', 0);
});

test('comment markup is displayed as text', function () {
    $body = '<script>alert("test")</script>';

    $this->actingAs($this->owner)
        ->post($this->url.'/comments', ['body' => $body])
        ->assertSessionHasNoErrors();

    $this->get($this->url)
        ->assertOk()
        ->assertSee(e($body), false)
        ->assertDontSee($body, false);
});

test('removing owner access prevents further reading and commenting', function () {
    $this->property->members()->detach($this->owner->id);

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertForbidden();

    $this->post($this->url.'/comments', [
        'body' => 'No longer authorized.',
    ])->assertForbidden();

    $this->assertDatabaseCount('defect_comments', 0);
});

test('workflow history preserves earlier repair notes after another repair', function () {
    $this->actingAs($this->contractor)
        ->patch($this->url.'/start')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->post($this->url.'/repair', [
        'repair_notes' => 'First repair: replaced the cracked tile.',
        'photos' => [UploadedFile::fake()->image('first.jpg')],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($this->manager)
        ->patch($this->url.'/verify')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->patch($this->url.'/reopen', [
        'reopen_reason' => 'The replacement tile is loose.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->actingAs($this->contractor)
        ->patch($this->url.'/start')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->post($this->url.'/repair', [
        'repair_notes' => 'Second repair: secured the replacement tile.',
        'photos' => [UploadedFile::fake()->image('second.jpg')],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $activities = $this->defect->activities()->orderBy('id')->get();

    expect($activities->pluck('event')->all())->toBe([
        'reported',
        'assigned',
        'work_started',
        'repaired',
        'verified',
        'reopened',
        'work_started',
        'repaired',
    ]);

    expect($activities->pluck('user_id')->all())->toBe([
        $this->manager->id,
        $this->manager->id,
        $this->contractor->id,
        $this->contractor->id,
        $this->manager->id,
        $this->manager->id,
        $this->contractor->id,
        $this->contractor->id,
    ]);

    $repairs = $activities->where('event', 'repaired')->values();

    expect($repairs[0]->metadata['repair_notes'])
        ->toBe('First repair: replaced the cracked tile.')
        ->and($repairs[1]->metadata['repair_notes'])
        ->toBe('Second repair: secured the replacement tile.');

    foreach ($repairs as $repair) {
        expect($repair->metadata['photo_paths'])->toHaveCount(1);

        foreach ($repair->metadata['photo_paths'] as $path) {
            Storage::disk('defect_photos')->assertExists($path);
        }
    }

    $reopened = $activities->firstWhere('event', 'reopened');

    expect($reopened->metadata['reason'])
        ->toBe('The replacement tile is loose.');

    $this->get($this->url)
        ->assertOk()
        ->assertSee('First repair: replaced the cracked tile.')
        ->assertSee('Second repair: secured the replacement tile.')
        ->assertSee('The replacement tile is loose.');
});

test('rejected workflow actions do not create activity entries', function () {
    $count = $this->defect->activities()->count();

    $this->actingAs($this->outsider)
        ->patch($this->url.'/start')
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->patch($this->url.'/verify')
        ->assertForbidden();

    $this->patch($this->url.'/assignment', [
        'assigned_to' => $this->owner->id,
        'priority' => 'high',
        'due_date' => now()->addDay()->format('Y-m-d'),
    ])->assertSessionHasErrors('assigned_to');

    expect($this->defect->activities()->count())->toBe($count);
});
