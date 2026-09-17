<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'manager' => UserRole::Manager,
        'owner' => UserRole::Owner,
        'contractor' => UserRole::Contractor,
    ] as $name => $role) {
        $user = User::factory()->create();
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        $this->{$name} = $user;
    }
});

test('guests must log in to manage users', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));

    $this->patch(route('users.status.update', $this->contractor), [
        'is_active' => false,
    ])->assertRedirect(route('login'));

    expect($this->contractor->fresh()->is_active)->toBeTrue();
});

test('owners and contractors cannot manage users', function (string $role) {
    $this->actingAs($this->{$role})
        ->get(route('users.index'))
        ->assertForbidden();

    $this->patch(route('users.status.update', $this->owner), [
        'is_active' => false,
    ])->assertForbidden();

    expect($this->owner->fresh()->is_active)->toBeTrue();
})->with(['owner', 'contractor']);

test('manager can view users deactivate and reactivate an account', function () {
    $this->actingAs($this->manager)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee($this->contractor->email);

    $this->patch(route('users.status.update', $this->contractor), [
        'is_active' => false,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($this->contractor->fresh()->is_active)->toBeFalse();

    $this->patch(route('users.status.update', $this->contractor), [
        'is_active' => true,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($this->contractor->fresh()->is_active)->toBeTrue();

    $this->assertDatabaseCount('users', 3);
});

test('manager cannot deactivate their own account', function () {
    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->manager), [
            'is_active' => false,
        ])
        ->assertSessionHasErrors('is_active');

    expect($this->manager->fresh()->is_active)->toBeTrue();
});

test('manager can deactivate another manager while retaining an active manager', function () {
    $otherManager = User::factory()->create();
    $otherManager->role = UserRole::Manager;
    $otherManager->save();

    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $otherManager), [
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($otherManager->fresh()->is_active)->toBeFalse()
        ->and($this->manager->fresh()->is_active)->toBeTrue();
});

test('status updates require a boolean value', function (array $payload) {
    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->contractor), $payload)
        ->assertSessionHasErrors('is_active');

    expect($this->contractor->fresh()->is_active)->toBeTrue();
})->with([
    'missing' => [[]],
    'invalid' => [['is_active' => 'invalid']],
]);

test('status updates do not change roles or passwords', function () {
    $password = $this->contractor->password;

    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->contractor), [
            'is_active' => false,
            'role' => UserRole::Manager->value,
            'password' => 'unauthorized-change',
        ])
        ->assertSessionHasNoErrors();

    $contractor = $this->contractor->fresh();

    expect($contractor->role)->toBe(UserRole::Contractor)
        ->and($contractor->password)->toBe($password)
        ->and($contractor->is_active)->toBeFalse();
});

test('inactive users cannot log in with a correct password', function () {
    $this->contractor->is_active = false;
    $this->contractor->save();

    $this->post(route('login.store'), [
        'email' => $this->contractor->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('reactivated users can log in again', function () {
    $this->contractor->is_active = false;
    $this->contractor->save();

    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->contractor), [
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $this->contractor->email,
        'password' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->contractor);
});

test('inactive users are blocked before the two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();
    $user->is_active = false;
    $user->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('login.id');

    $this->assertGuest();
});

test('inactive authenticated users are logged out on their next request', function () {
    $this->contractor->is_active = false;
    $this->contractor->save();

    $this->actingAs($this->contractor)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive authenticated users receive forbidden JSON responses', function () {
    $this->contractor->is_active = false;
    $this->contractor->save();

    $this->actingAs($this->contractor)
        ->getJson(route('dashboard'))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Your account is inactive. Please contact a manager.',
        ]);

    $this->assertGuest();
});

test('deactivation removes target database sessions and rotates remember token', function () {
    config([
        'session.driver' => 'database',
        'session.connection' => config('database.default'),
        'session.table' => 'sessions',
    ]);

    $this->contractor->setRememberToken('previous-remember-token');
    $this->contractor->save();

    foreach ([
        'contractor-session-one' => $this->contractor->id,
        'contractor-session-two' => $this->contractor->id,
        'owner-session' => $this->owner->id,
    ] as $id => $userId) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Deactivation test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }

    $this->actingAs($this->manager)
        ->patch(route('users.status.update', $this->contractor), [
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('sessions', [
        'user_id' => $this->contractor->id,
    ]);

    $this->assertDatabaseHas('sessions', [
        'id' => 'owner-session',
        'user_id' => $this->owner->id,
    ]);

    expect($this->contractor->fresh()->remember_token)
        ->not->toBe('previous-remember-token');
});

test('account status cannot be mass assigned', function () {
    $this->contractor->fill(['is_active' => false]);
    $this->contractor->save();

    expect($this->contractor->fresh()->is_active)->toBeTrue();
});
