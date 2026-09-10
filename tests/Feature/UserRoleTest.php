<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('new users default to the owner role', function () {
    $user = User::factory()->create();

    expect($user->refresh()->role)->toBe(UserRole::Owner);
});

test('only managers can manage users', function (
    UserRole $role,
    bool $allowed,
) {
    $user = User::factory()->create();
    $user->role = $role;
    $user->save();
    $user->refresh();

    expect($user->role)->toBe($role)
        ->and(Gate::forUser($user)->allows('manage-users'))
        ->toBe($allowed);
})->with([
    'manager' => [UserRole::Manager, true],
    'contractor' => [UserRole::Contractor, false],
    'owner' => [UserRole::Owner, false],
]);

test('guests cannot manage users', function () {
    expect(Gate::allows('manage-users'))->toBeFalse();
});

test('role cannot be mass assigned', function () {
    $user = new User;

    expect($user->isFillable('role'))->toBeFalse();
});
