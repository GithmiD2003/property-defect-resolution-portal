<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function invitationTestUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->save();

    return $user;
}

/**
 * @return array{0: Invitation, 1: string}
 */
function invitationTestRecord(
    User $manager,
    ?Property $property = null,
): array {
    $token = Str::random(64);

    $invitation = new Invitation;
    $invitation->email = 'invited.owner@example.test';
    $invitation->role = UserRole::Owner;
    $invitation->token_hash = hash('sha256', $token);
    $invitation->invited_by = $manager->id;
    $invitation->property_id = $property?->id;
    $invitation->expires_at = now()->addHours(48);
    $invitation->save();

    return [$invitation, $token];
}

test('managers can create and deliver owner invitations', function () {
    Notification::fake();

    $manager = invitationTestUser(UserRole::Manager);

    $this->actingAs($manager)
        ->post(route('invitations.store'), [
            'email' => 'new.owner@example.test',
            'role' => 'owner',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invitations.index'));

    $invitation = Invitation::query()->sole();

    expect($invitation->email)->toBe('new.owner@example.test')
        ->and($invitation->role)->toBe(UserRole::Owner)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        UserInvitation::class,
        function ($notification, $channels, $notifiable) use ($invitation) {
            parse_str(
                parse_url($notification->acceptUrl, PHP_URL_QUERY) ?? '',
                $query,
            );

            $token = $query['token'] ?? '';

            return is_string($token)
                && strlen($token) === 64
                && hash('sha256', $token) === $invitation->token_hash
                && $notifiable->routes['mail'] === $invitation->email;
        },
    );
});

test('owners and contractors cannot create invitations', function (
    UserRole $role,
) {
    Notification::fake();

    $user = invitationTestUser($role);

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertForbidden();

    $this->post(route('invitations.store'), [
        'email' => 'another@example.test',
        'role' => 'owner',
    ])->assertForbidden();

    $this->assertDatabaseCount('invitations', 0);

    Notification::assertNothingSent();
})->with([
    'owner' => [UserRole::Owner],
    'contractor' => [UserRole::Contractor],
]);

test('manager role cannot be granted through invitations', function () {
    Notification::fake();

    $manager = invitationTestUser(UserRole::Manager);

    $this->actingAs($manager)
        ->post(route('invitations.store'), [
            'email' => 'another.manager@example.test',
            'role' => 'manager',
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseCount('invitations', 0);

    Notification::assertNothingSent();
});

test('acceptance creates the invited owner and property membership', function () {
    $manager = invitationTestUser(UserRole::Manager);

    $property = new Property([
        'name' => 'Invited Property',
        'address' => '123 Example Road',
    ]);

    $property->creator()->associate($manager);
    $property->save();

    [$invitation, $token] = invitationTestRecord($manager, $property);

    $this->get(route('invitations.accept.show', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertSee('Set up your account');

    $this->post(route('invitations.accept.store', $invitation), [
        'token' => $token,
        'name' => 'Invited Owner',
        'password' => 'ExamplePassword123!',
        'password_confirmation' => 'ExamplePassword123!',
        'role' => 'manager',
        'email' => 'changed@example.test',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user = User::query()
        ->where('email', $invitation->email)
        ->firstOrFail();

    expect($user->role)->toBe(UserRole::Owner)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('ExamplePassword123!', $user->password))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertDatabaseHas('property_members', [
        'property_id' => $property->id,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'changed@example.test',
    ]);

    $this->post(route('invitations.accept.store', $invitation), [
        'token' => $token,
        'name' => 'Second Attempt',
        'password' => 'ExamplePassword123!',
        'password_confirmation' => 'ExamplePassword123!',
    ])->assertStatus(410);

    $this->assertDatabaseCount('users', 2);
});

test('invalid invitation tokens are rejected', function () {
    $manager = invitationTestUser(UserRole::Manager);

    [$invitation] = invitationTestRecord($manager);

    $wrongToken = Str::random(64);

    $this->get(route('invitations.accept.show', [
        'invitation' => $invitation->id,
        'token' => $wrongToken,
    ]))->assertNotFound();

    $this->post(route('invitations.accept.store', $invitation), [
        'token' => $wrongToken,
        'name' => 'Invalid Attempt',
        'password' => 'ExamplePassword123!',
        'password_confirmation' => 'ExamplePassword123!',
    ])->assertNotFound();

    $this->assertDatabaseCount('users', 1);
});

test('expired and revoked invitations cannot be accepted', function (
    string $state,
) {
    $manager = invitationTestUser(UserRole::Manager);

    [$invitation, $token] = invitationTestRecord($manager);

    if ($state === 'expired') {
        $invitation->expires_at = now()->subMinute();
    } else {
        $invitation->revoked_at = now();
    }

    $invitation->save();

    $this->get(route('invitations.accept.show', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertStatus(410);

    $this->post(route('invitations.accept.store', $invitation), [
        'token' => $token,
        'name' => 'Invalid Attempt',
        'password' => 'ExamplePassword123!',
        'password_confirmation' => 'ExamplePassword123!',
    ])->assertStatus(410);

    $this->assertDatabaseCount('users', 1);
})->with([
    'expired',
    'revoked',
]);

test('only managers can revoke pending invitations', function () {
    $manager = invitationTestUser(UserRole::Manager);
    $owner = invitationTestUser(UserRole::Owner);

    [$invitation] = invitationTestRecord($manager);

    $this->actingAs($owner)
        ->delete(route('invitations.destroy', $invitation))
        ->assertForbidden();

    expect($invitation->fresh()->revoked_at)->toBeNull();

    $this->actingAs($manager)
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect(route('invitations.index'));

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});
