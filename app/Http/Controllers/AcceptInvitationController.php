<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    public function show(
        Request $request,
        Invitation $invitation,
    ): View {
        $token = $request->query('token');

        abort_unless(is_string($token), 404);

        $this->ensureUsable($invitation, $token);

        return view('invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function store(
        Request $request,
        Invitation $invitation,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->numbers(),
            ],
        ]);

        $this->ensureUsable($invitation, $validated['token']);

        $passwordHash = Hash::make($validated['password']);

        try {
            DB::transaction(function () use (
                $invitation,
                $validated,
                $passwordHash,
            ): void {
                $locked = Invitation::query()
                    ->lockForUpdate()
                    ->findOrFail($invitation->id);

                $this->ensureUsable($locked, $validated['token']);

                if (User::query()->where('email', $locked->email)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'An account already exists for this email. Please log in.',
                    ]);
                }

                $user = new User;
                $user->name = $validated['name'];
                $user->email = $locked->email;
                $user->password = $passwordHash;
                $user->role = $locked->role;
                $user->email_verified_at = now();
                $user->save();

                if (
                    $locked->role === UserRole::Owner
                    && $locked->property_id !== null
                ) {
                    $property = Property::query()
                        ->findOrFail($locked->property_id);

                    $property->members()->syncWithoutDetaching([
                        $user->id,
                    ]);
                }

                $locked->accepted_at = now();
                $locked->save();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account already exists for this email. Please log in.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your account is ready. Please log in.');
    }

    private function ensureUsable(
        Invitation $invitation,
        string $token,
    ): void {
        abort_unless(
            strlen($token) === 64
            && hash_equals(
                $invitation->token_hash,
                hash('sha256', $token),
            ),
            404,
        );

        abort_unless(
            $invitation->isPending(),
            410,
            'This invitation has expired, was revoked, or has already been accepted.',
        );

        abort_unless(
            in_array($invitation->role, [
                UserRole::Owner,
                UserRole::Contractor,
            ], true),
            403,
        );
    }
}
