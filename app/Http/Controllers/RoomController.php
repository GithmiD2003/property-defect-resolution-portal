<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function store(
        Request $request,
        Property $property,
    ): RedirectResponse {
        Gate::authorize('manageRooms', $property);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('rooms', 'name')
                    ->where('property_id', $property->getKey()),
            ],
        ]);

        $property->rooms()->create($validated);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Room added successfully.');
    }
}
