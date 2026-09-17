<?php

use App\Enums\DefectStatus;
use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function handoverTestDefect(
    Property $property,
    User $manager,
    DefectStatus $status,
    string $title,
): Defect {
    $room = $property->rooms()->firstOrFail();

    $defect = new Defect([
        'title' => $title,
        'description' => 'Description for '.$title,
        'category' => 'plumbing',
    ]);

    $defect->property()->associate($property);
    $defect->room()->associate($room);
    $defect->reporter()->associate($manager);
    $defect->status = $status;
    $defect->priority = 'medium';

    if ($status === DefectStatus::Verified) {
        $defect->reviewer()->associate($manager);
        $defect->verified_at = now();
    }

    $defect->save();

    return $defect;
}

beforeEach(function () {
    foreach ([
        'manager' => UserRole::Manager,
        'owner' => UserRole::Owner,
        'contractor' => UserRole::Contractor,
    ] as $name => $role) {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        $this->{$name} = $user;
    }

    $this->property = new Property([
        'name' => 'Handover Test House',
        'address' => '123 Example Road',
        'target_handover_date' => '2026-10-01',
    ]);
    $this->property->creator()->associate($this->manager);
    $this->property->save();
    $this->property->rooms()->create(['name' => 'Kitchen']);
    $this->property->members()->attach($this->owner->id);

    $this->url = route('properties.handover-report', $this->property);
});

test('guests must log in to access handover reports', function () {
    $this->get($this->url)
        ->assertRedirect(route('login'));
});

test('owners and contractors cannot generate handover reports', function (
    string $actor,
) {
    $this->actingAs($this->{$actor})
        ->get($this->url)
        ->assertForbidden();
})->with(['owner', 'contractor']);

test('manager report separates verified defects from every unresolved status', function () {
    $verifiedIds = [];
    $unresolvedIds = [];

    foreach (DefectStatus::cases() as $status) {
        $defect = handoverTestDefect(
            $this->property,
            $this->manager,
            $status,
            'Defect '.$status->value,
        );

        if ($status === DefectStatus::Verified) {
            $verifiedIds[] = $defect->id;
        } else {
            $unresolvedIds[] = $defect->id;
        }
    }

    $response = $this->actingAs($this->manager)
        ->get($this->url)
        ->assertOk()
        ->assertViewHas('total', 6)
        ->assertViewHas(
            'verified',
            fn ($items) => $items->modelKeys() === $verifiedIds,
        )
        ->assertViewHas(
            'unresolved',
            fn ($items) => $items->modelKeys() === $unresolvedIds,
        )
        ->assertSee('Handover Test House')
        ->assertSee('123 Example Road')
        ->assertSee('01 Oct 2026')
        ->assertSee('Unresolved defects (5)')
        ->assertSee('Verified defects (1)')
        ->assertSee('Outstanding defects remain.')
        ->assertSee('Defect repaired')
        ->assertSee($this->manager->name)
        ->assertSeeInOrder([
            'Unresolved defects (5)',
            'Verified defects (1)',
        ]);

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
});

test('report excludes defects from other properties', function () {
    $included = handoverTestDefect(
        $this->property,
        $this->manager,
        DefectStatus::Reported,
        'Included kitchen leak',
    );

    $otherProperty = new Property([
        'name' => 'Other Private House',
        'address' => '456 Other Road',
    ]);
    $otherProperty->creator()->associate($this->manager);
    $otherProperty->save();
    $otherProperty->rooms()->create(['name' => 'Bathroom']);

    handoverTestDefect(
        $otherProperty,
        $this->manager,
        DefectStatus::Verified,
        'Other property secret repair',
    );

    $this->actingAs($this->manager)
        ->get($this->url)
        ->assertOk()
        ->assertViewHas('total', 1)
        ->assertViewHas(
            'unresolved',
            fn ($items) => $items->modelKeys() === [$included->id],
        )
        ->assertViewHas('verified', fn ($items) => $items->isEmpty())
        ->assertSee('Included kitchen leak')
        ->assertDontSee('Other property secret repair')
        ->assertDontSee('Other Private House');
});

test('empty property report does not imply an inspection was completed', function () {
    $this->actingAs($this->manager)
        ->get($this->url)
        ->assertOk()
        ->assertViewHas('total', 0)
        ->assertViewHas('verified', fn ($items) => $items->isEmpty())
        ->assertViewHas('unresolved', fn ($items) => $items->isEmpty())
        ->assertSee('No defects have been recorded for this property.')
        ->assertSee('This does not confirm that an inspection has been completed.')
        ->assertDontSee('All recorded defects are verified.');
});

test('fully verified report does not claim formal handover approval', function () {
    handoverTestDefect(
        $this->property,
        $this->manager,
        DefectStatus::Verified,
        'Verified kitchen repair',
    );

    $this->actingAs($this->manager)
        ->get($this->url)
        ->assertOk()
        ->assertViewHas('total', 1)
        ->assertViewHas('unresolved', fn ($items) => $items->isEmpty())
        ->assertSee('All recorded defects are verified.')
        ->assertSee('This report does not replace formal handover approval.');
});

test('report escapes user supplied text', function () {
    $title = '<script>alert("report")</script>';

    handoverTestDefect(
        $this->property,
        $this->manager,
        DefectStatus::Reported,
        $title,
    );

    $this->actingAs($this->manager)
        ->get($this->url)
        ->assertOk()
        ->assertSee(e($title), false)
        ->assertDontSee($title, false);
});

test('report link is visible to managers but hidden from owners', function () {
    $propertyUrl = route('properties.show', $this->property);
    $reportLink = 'href="'.$this->url.'"';

    $this->actingAs($this->manager)
        ->get($propertyUrl)
        ->assertOk()
        ->assertSee($reportLink, false);

    $this->actingAs($this->owner)
        ->get($propertyUrl)
        ->assertOk()
        ->assertDontSee($reportLink, false);
});
