<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PropertyMemberController extends Controller
{
    public function store(
        Request $request,
        Property $property,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $property);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Owner->value),
            ],
        ]);

        $property->members()->syncWithoutDetaching([
            $validated['user_id'],
        ]);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Owner assigned successfully.');
    }

    public function destroy(
        Property $property,
        User $user,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $property);

        abort_unless(
            $property->members()
                ->where('users.id', $user->getKey())
                ->exists(),
            404,
        );

        $property->members()->detach($user->getKey());

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property access removed.');
    }
}
