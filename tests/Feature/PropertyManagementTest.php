<?php

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function propertyTestUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->save();

    return $user;
}

function propertyTestRecord(User $manager): Property
{
    $property = new Property([
        'name' => 'Test House',
        'address' => '123 Example Road',
    ]);

    $property->creator()->associate($manager);
    $property->save();

    return $property;
}

test('guests must log in to view properties', function () {
    $this->get(route('properties.index'))
        ->assertRedirect(route('login'));
});

test('managers can create properties and add rooms', function () {
    $manager = propertyTestUser(UserRole::Manager);

    $this->actingAs($manager)
        ->post(route('properties.store'), [
            'name' => 'New House',
            'address' => '456 Example Road',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $property = Property::query()->sole();

    expect($property->name)->toBe('New House')
        ->and($property->created_by)->toBe($manager->id);

    $this->post(route('properties.rooms.store', $property), [
        'name' => 'Kitchen',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('rooms', [
        'property_id' => $property->id,
        'name' => 'Kitchen',
    ]);
});

test('owners only see properties linked to their account', function () {
    $manager = propertyTestUser(UserRole::Manager);
    $owner = propertyTestUser(UserRole::Owner);

    $linked = propertyTestRecord($manager);
    $linked->update(['name' => 'Owner Accessible House']);
    $linked->members()->attach($owner->id);

    $other = propertyTestRecord($manager);
    $other->update(['name' => 'Private Other House']);

    $this->actingAs($owner)
        ->get(route('properties.index'))
        ->assertOk()
        ->assertSee('Owner Accessible House')
        ->assertDontSee('Private Other House');

    $this->get(route('properties.show', $linked))->assertOk();
    $this->get(route('properties.show', $other))->assertForbidden();
});

test('owners and contractors cannot manage properties', function (
    UserRole $role,
) {
    $manager = propertyTestUser(UserRole::Manager);
    $user = propertyTestUser($role);
    $property = propertyTestRecord($manager);

    $property->members()->attach($user->id);

    $this->actingAs($user)
        ->get(route('properties.create'))
        ->assertForbidden();

    $this->post(route('properties.store'), [
        'name' => 'Unauthorised House',
        'address' => 'Example address',
    ])->assertForbidden();

    $this->get(route('properties.edit', $property))
        ->assertForbidden();

    $this->patch(route('properties.update', $property), [
        'name' => 'Unauthorised Change',
        'address' => 'Example address',
    ])->assertForbidden();

    $this->post(route('properties.rooms.store', $property), [
        'name' => 'Unauthorised Room',
    ])->assertForbidden();

    expect($property->fresh()->name)->toBe('Test House');
    $this->assertDatabaseCount('properties', 1);
    $this->assertDatabaseCount('rooms', 0);
})->with([
    'owner' => [UserRole::Owner],
    'contractor' => [UserRole::Contractor],
]);

test('contractors cannot browse properties even when linked', function () {
    $manager = propertyTestUser(UserRole::Manager);
    $contractor = propertyTestUser(UserRole::Contractor);
    $property = propertyTestRecord($manager);

    $property->members()->attach($contractor->id);

    $this->actingAs($contractor)
        ->get(route('properties.index'))
        ->assertForbidden();

    $this->get(route('properties.show', $property))
        ->assertForbidden();
});

test('room names must be unique within a property', function () {
    $manager = propertyTestUser(UserRole::Manager);
    $first = propertyTestRecord($manager);
    $second = propertyTestRecord($manager);

    $first->rooms()->create(['name' => 'Kitchen']);

    $this->actingAs($manager)
        ->post(route('properties.rooms.store', $first), [
            'name' => 'Kitchen',
        ])
        ->assertSessionHasErrors('name');

    $this->post(route('properties.rooms.store', $second), [
        'name' => 'Kitchen',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseCount('rooms', 2);
});
