<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DefectReviewController extends Controller
{
    public function verify(
        Request $request,
        Defect $defect,
    ): RedirectResponse {
        Gate::authorize('verifyRepair', $defect);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        DB::transaction(function () use ($defect, $user): void {
            $locked = Defect::query()
                ->lockForUpdate()
                ->findOrFail($defect->id);

            Gate::authorize('verifyRepair', $locked);

            $locked->reviewer()->associate($user);
            $locked->verified_at = now();
            $locked->status = DefectStatus::Verified;
            $locked->save();

            $locked->recordActivity(
                $user,
                'verified',
                'Repair verified.',
            );
        });

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Repair verified successfully.');
    }

    public function reopen(
        Request $request,
        Defect $defect,
    ): RedirectResponse {
        Gate::authorize('reopen', $defect);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'reopen_reason' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use (
            $defect,
            $user,
            $validated,
        ): void {
            $locked = Defect::query()
                ->lockForUpdate()
                ->findOrFail($defect->id);

            Gate::authorize('reopen', $locked);

            $locked->reviewer()->associate($user);
            $locked->reopen_reason = $validated['reopen_reason'];
            $locked->reopened_at = now();
            $locked->verified_at = null;
            $locked->status = DefectStatus::Reopened;
            $locked->save();

            $locked->recordActivity(
                $user,
                'reopened',
                'Defect reopened for further repair.',
                ['reason' => $validated['reopen_reason']],
            );
        });

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Defect reopened for further repair.');
    }
}
