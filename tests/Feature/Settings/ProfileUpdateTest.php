<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('profile shows deactivation guidance without a delete button', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('To deactivate your account, contact a company manager.')
        ->assertDontSee('data-test="delete-user-button"', false)
        ->assertDontSee('wire:submit="deleteUser"', false);
});

test('account deletion is forbidden for every role even with a correct password', function (string $role) {
    $user = User::factory()->create();
    $user->role = UserRole::from($role);
    $user->save();

    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_active' => true,
    ]);

    $this->assertAuthenticatedAs($user);
})->with(['manager', 'owner', 'contractor']);

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
