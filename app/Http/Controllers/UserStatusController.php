<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserStatusController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('users.index', [
            'users' => User::query()
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-users');

        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $active = $request->boolean('is_active');

        DB::transaction(function () use ($actor, $user, $active): void {
            // Serialize manager status changes to prevent mutual lockout.
            $managers = User::query()
                ->where('role', UserRole::Manager->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentActor = $managers->firstWhere('id', $actor->id);

            abort_unless(
                $currentActor instanceof User && $currentActor->is_active,
                403,
            );

            $target = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            if (! $active && $target->id === $actor->id) {
                throw ValidationException::withMessages([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
            }

            if (
                ! $active
                && $target->role === UserRole::Manager
                && $target->is_active
                && $managers->where('is_active', true)->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'is_active' => 'At least one manager must remain active.',
                ]);
            }

            if ($target->is_active === $active) {
                return;
            }

            $target->is_active = $active;

            // Invalidate existing remember-me cookies.
            $target->setRememberToken(Str::random(60));
            $target->save();

            if (! $active && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $target->id)
                    ->delete();
            }
        }, 3);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                $active
                    ? 'User reactivated successfully.'
                    : 'User deactivated successfully.',
            );
    }
}
