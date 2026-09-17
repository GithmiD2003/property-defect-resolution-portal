<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\Property;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class HandoverReportController extends Controller
{
    public function show(Property $property): Response
    {
        Gate::authorize('handoverReport', $property);

        $defects = Defect::query()
            ->where('property_id', $property->id)
            ->with(['room', 'assignee', 'reviewer'])
            ->orderBy('id')
            ->get();

        $verified = $defects->filter(
            fn (Defect $defect): bool => $defect->status === DefectStatus::Verified,
        )->values();

        $unresolved = $defects->filter(
            fn (Defect $defect): bool => $defect->status !== DefectStatus::Verified,
        )->values();

        return response()
            ->view('reports.handover', [
                'property' => $property,
                'total' => $defects->count(),
                'verified' => $verified,
                'unresolved' => $unresolved,
                'generatedAt' => now(),
            ])
            ->header('Cache-Control', 'private, no-store');
    }
}
