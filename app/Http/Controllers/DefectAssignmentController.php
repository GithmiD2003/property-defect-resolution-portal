<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DefectAssignmentController extends Controller
{
    public function update(Request $request, Defect $defect): RedirectResponse
    {
        Gate::authorize('assign', $defect);

        $validated = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Contractor->value),
            ],
            'priority' => [
                'required',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],
            'due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
        ]);

        DB::transaction(function () use ($defect, $validated): void {
            $locked = Defect::query()
                ->lockForUpdate()
                ->findOrFail($defect->id);

            // Check again in case the status changed while the form was open.
            Gate::authorize('assign', $locked);

            $contractor = User::query()
                ->whereKey($validated['assigned_to'])
                ->where('role', UserRole::Contractor->value)
                ->lockForUpdate()
                ->first();

            if ($contractor === null) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Please select an available contractor.',
                ]);
            }

            $locked->assignee()->associate($contractor);
            $locked->priority = $validated['priority'];
            $locked->setAttribute('due_date', $validated['due_date']);
            $locked->status = DefectStatus::Assigned;
            $locked->save();
        });

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Contractor assignment saved successfully.');
    }
}
