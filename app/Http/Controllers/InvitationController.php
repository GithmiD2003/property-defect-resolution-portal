<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Property;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class InvitationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('invitations.index', [
            'invitations' => Invitation::query()
                ->latest()
                ->paginate(15),
            'properties' => Property::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $manager = $request->user();
        abort_unless($manager instanceof User, 401);

        $request->merge([
            'email' => Str::lower(trim($request->string('email')->toString())),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'role' => [
                'required',
                Rule::in([
                    UserRole::Owner->value,
                    UserRole::Contractor->value,
                ]),
            ],
            'property_id' => [
                'nullable',
                'integer',
                'prohibited_unless:role,owner',
                Rule::exists('properties', 'id'),
            ],
        ]);

        $token = Str::random(64);

        $invitation = DB::transaction(function () use (
            $validated,
            $manager,
            $token,
        ): Invitation {
            Invitation::query()
                ->where('email', $validated['email'])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = new Invitation;
            $invitation->email = $validated['email'];
            $invitation->role = UserRole::from($validated['role']);
            $invitation->token_hash = hash('sha256', $token);
            $invitation->invited_by = $manager->id;
            $invitation->property_id = $validated['property_id'] ?? null;
            $invitation->expires_at = now()->addHours(48);
            $invitation->save();

            return $invitation;
        });

        $url = route('invitations.accept.show', [
            'invitation' => $invitation->id,
            'token' => $token,
        ]);

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new UserInvitation($url, $invitation->role));
        } catch (Throwable $exception) {
            $invitation->revoked_at = now();
            $invitation->save();

            report($exception);

            return back()
                ->withErrors([
                    'email' => 'The invitation could not be delivered. Please try again.',
                ])
                ->withInput($request->only('email', 'role', 'property_id'));
        }

        $message = config('mail.default') === 'log'
            ? 'Invitation created. The email was written to the local mail log.'
            : 'Invitation submitted to the email service.';

        return redirect()
            ->route('invitations.index')
            ->with('success', $message);
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manage-users');

        DB::transaction(function () use ($invitation): void {
            $locked = Invitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            abort_unless($locked->isPending(), 409, 'Invitation is no longer pending.');

            $locked->revoked_at = now();
            $locked->save();
        });

        return redirect()
            ->route('invitations.index')
            ->with('success', 'Invitation revoked.');
    }
}
