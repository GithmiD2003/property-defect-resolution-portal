<?php

use App\Enums\DefectStatus;
use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardFilterDefect(
    Property $property,
    User $reporter,
    User $contractor,
    string $title,
    DefectStatus $status,
    string $priority,
    ?string $dueDate,
): Defect {
    $room = $property->rooms()->firstOrFail();

    $defect = new Defect([
        'title' => $title,
        'description' => 'Test defect description.',
        'category' => 'plumbing',
    ]);

    $defect->property()->associate($property);
    $defect->room()->associate($room);
    $defect->reporter()->associate($reporter);
    $defect->assignee()->associate($contractor);
    $defect->status = $status;
    $defect->priority = $priority;
    $defect->setAttribute('due_date', $dueDate);
    $defect->save();

    return $defect;
}

beforeEach(function () {
    $this->travelTo(
        CarbonImmutable::parse('2026-09-16 12:00:00'),
    );

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

    foreach ([
        'linkedProperty' => 'Accessible House',
        'privateProperty' => 'Secret House',
    ] as $name => $title) {
        $property = new Property([
            'name' => $title,
            'address' => '123 Example Road',
        ]);
        $property->creator()->associate($this->manager);
        $property->save();
        $property->rooms()->create(['name' => 'Kitchen']);

        $this->{$name} = $property;
    }

    $this->linkedProperty->members()->attach($this->owner->id);

    $this->overdue = dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->contractor,
        'Overdue leaking tap',
        DefectStatus::Assigned,
        'high',
        '2026-09-15',
    );

    $this->dueToday = dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->otherContractor,
        'Repair due today',
        DefectStatus::InProgress,
        'medium',
        '2026-09-16',
    );

    $this->repaired = dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->contractor,
        'Completed tap repair',
        DefectStatus::Repaired,
        'low',
        '2026-09-14',
    );

    $this->verified = dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->contractor,
        'Verified tap repair',
        DefectStatus::Verified,
        'low',
        '2026-09-14',
    );

    $this->privateDefect = dashboardFilterDefect(
        $this->privateProperty,
        $this->manager,
        $this->otherContractor,
        'Secret leaking tap',
        DefectStatus::Assigned,
        'high',
        '2026-09-15',
    );
});

afterEach(function () {
    $this->travelBack();
});

test('dashboard totals respect each users access', function (
    string $actor,
    int $total,
    int $overdue,
    int $assigned,
    int $inProgress,
    int $repaired,
    int $verified,
) {
    $this->actingAs($this->{$actor})
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('total', $total)
        ->assertViewHas('overdueCount', $overdue)
        ->assertViewHas('counts', [
            'reported' => 0,
            'assigned' => $assigned,
            'in_progress' => $inProgress,
            'repaired' => $repaired,
            'verified' => $verified,
            'reopened' => 0,
        ]);
})->with([
    'manager' => ['manager', 5, 2, 2, 1, 1, 1],
    'linked owner' => ['owner', 4, 1, 1, 1, 1, 1],
    'assigned contractor' => ['contractor', 3, 1, 1, 0, 1, 1],
    'unlinked owner' => ['outsider', 0, 0, 0, 0, 0, 0],
]);

test('dashboard recent reports exclude inaccessible defects', function (
    string $actor,
) {
    $this->actingAs($this->{$actor})
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Overdue leaking tap')
        ->assertDontSee('Secret leaking tap')
        ->assertDontSee('Secret House');
})->with(['owner', 'contractor']);

test('overdue filtering excludes today repaired verified and undated defects', function () {
    dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->contractor,
        'No due date',
        DefectStatus::Reported,
        'medium',
        null,
    );

    dashboardFilterDefect(
        $this->linkedProperty,
        $this->owner,
        $this->contractor,
        'Future repair',
        DefectStatus::Assigned,
        'medium',
        '2026-09-20',
    );

    $this->actingAs($this->owner)
        ->get(route('defects.index', ['overdue' => 1]))
        ->assertOk()
        ->assertViewHas('defects', function ($defects) {
            return $defects->total() === 1
                && $defects->getCollection()->modelKeys() === [
                    $this->overdue->id,
                ];
        });
});

test('reopened defects with past due dates count as overdue', function () {
    $this->verified->status = DefectStatus::Reopened;
    $this->verified->save();

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('overdueCount', 2);

    $this->get(route('defects.index', ['overdue' => 1]))
        ->assertOk()
        ->assertViewHas('defects', function ($defects) {
            return $defects->total() === 2
                && $defects->getCollection()->contains('id', $this->verified->id);
        });
});

test('search status and priority filters work together', function () {
    $this->actingAs($this->manager)
        ->get(route('defects.index', [
            'search' => 'Accessible House',
            'status' => 'assigned',
            'priority' => 'high',
        ]))
        ->assertOk()
        ->assertViewHas('defects', function ($defects) {
            return $defects->total() === 1
                && $defects->getCollection()->modelKeys() === [
                    $this->overdue->id,
                ];
        });
});

test('search matches defect titles', function () {
    $this->actingAs($this->owner)
        ->get(route('defects.index', [
            'search' => 'Completed tap',
        ]))
        ->assertOk()
        ->assertViewHas('defects', function ($defects) {
            return $defects->total() === 1
                && $defects->getCollection()->modelKeys() === [
                    $this->repaired->id,
                ];
        });
});

test('title and property searches cannot bypass access restrictions', function (
    string $actor,
    string $search,
) {
    $this->actingAs($this->{$actor})
        ->get(route('defects.index', ['search' => $search]))
        ->assertOk()
        ->assertViewHas('defects', fn ($defects) => $defects->total() === 0)
        ->assertDontSee('Secret leaking tap');
})->with([
    ['owner', 'Secret leaking'],
    ['owner', 'Secret House'],
    ['contractor', 'Secret leaking'],
    ['contractor', 'Secret House'],
]);

test('contractors cannot search other contractors work on the same property', function () {
    $this->actingAs($this->contractor)
        ->get(route('defects.index', [
            'search' => 'Repair due today',
        ]))
        ->assertOk()
        ->assertViewHas('defects', fn ($defects) => $defects->total() === 0);
});

test('empty filters return all accessible defects', function () {
    $this->actingAs($this->owner)
        ->get(route('defects.index', [
            'search' => '',
            'status' => '',
            'priority' => '',
        ]))
        ->assertOk()
        ->assertViewHas('defects', fn ($defects) => $defects->total() === 4);
});

test('invalid filter values are rejected', function (
    array $filters,
    string $field,
) {
    $this->actingAs($this->manager)
        ->getJson(route('defects.index', $filters))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    [['status' => 'invalid'], 'status'],
    [['priority' => 'invalid'], 'priority'],
    [['overdue' => 'invalid'], 'overdue'],
    [['search' => str_repeat('a', 101)], 'search'],
]);

test('pagination retains selected filters', function () {
    for ($i = 1; $i <= 16; $i++) {
        dashboardFilterDefect(
            $this->linkedProperty,
            $this->owner,
            $this->contractor,
            'Pagination sample '.$i,
            DefectStatus::Assigned,
            'high',
            '2026-09-20',
        );
    }

    $filters = [
        'search' => 'Pagination sample',
        'status' => 'assigned',
        'priority' => 'high',
    ];

    $response = $this->actingAs($this->owner)
        ->get(route('defects.index', $filters))
        ->assertOk();

    $defects = $response->viewData('defects');

    expect($defects->total())->toBe(16)
        ->and($defects->count())->toBe(15);

    $nextUrl = $defects->nextPageUrl();

    expect($nextUrl)->not->toBeNull();

    parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $query);

    expect($query['search'])->toBe('Pagination sample')
        ->and($query['status'])->toBe('assigned')
        ->and($query['priority'])->toBe('high')
        ->and($query['page'])->toBe('2');

    $this->get(route('defects.index', [...$filters, 'page' => 2]))
        ->assertOk()
        ->assertViewHas('defects', fn ($items) => $items->count() === 1);
});

test('guests cannot access filtered defect listings', function () {
    $this->get(route('defects.index', ['search' => 'tap']))
        ->assertRedirect(route('login'));
});
