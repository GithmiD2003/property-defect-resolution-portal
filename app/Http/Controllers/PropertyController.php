<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Property::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = Property::query()->withCount('rooms');

        if ($user->role !== UserRole::Manager) {
            $query->whereHas('members', function ($members) use ($user) {
                $members->where('users.id', $user->getKey());
            });
        }

        return view('properties.index', [
            'properties' => $query->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Property::class);

        return view('properties.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Property::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $property = new Property($this->validatedData($request));
        $property->creator()->associate($user);
        $property->save();

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property created successfully.');
    }

    public function show(Property $property): View
    {
        Gate::authorize('view', $property);

        $property->load([
            'rooms' => fn ($query) => $query->orderBy('name'),
            'members',
        ]);

        return view('properties.show', [
            'property' => $property,
        ]);
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        return view('properties.edit', [
            'property' => $property,
        ]);
    }

    public function update(
        Request $request,
        Property $property,
    ): RedirectResponse {
        Gate::authorize('update', $property);

        $property->update($this->validatedData($request));

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'target_handover_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
