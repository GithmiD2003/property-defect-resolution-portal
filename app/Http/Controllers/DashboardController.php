<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\User;
use App\Queries\VisibleDefects;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Defect::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = VisibleDefects::forUser($user);

        $counts = [];

        foreach (DefectStatus::cases() as $status) {
            $counts[$status->value] = (clone $query)
                ->where('status', $status->value)
                ->count();
        }

        // Awaiting verification is shown separately from outstanding repairs.
        $overdueQuery = (clone $query)
            ->whereDate('due_date', '<', now()->format('Y-m-d'))
            ->whereNotIn('status', [
                DefectStatus::Repaired->value,
                DefectStatus::Verified->value,
            ]);

        return view('dashboard', [
            'total' => array_sum($counts),
            'counts' => $counts,
            'statuses' => DefectStatus::cases(),
            'overdueCount' => (clone $overdueQuery)->count(),
            'overdueDefects' => (clone $overdueQuery)
                ->with(['property', 'assignee'])
                ->orderBy('due_date')
                ->orderBy('id')
                ->limit(5)
                ->get(),
            'recentDefects' => (clone $query)
                ->with(['property', 'assignee'])
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
