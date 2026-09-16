<?php

namespace App\Http\Controllers;

use App\Models\Defect;
use App\Models\DefectComment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DefectCommentController extends Controller
{
    public function store(
        Request $request,
        Defect $defect,
    ): RedirectResponse {
        Gate::authorize('comment', $defect);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($defect, $user, $validated): void {
            $locked = Defect::query()
                ->lockForUpdate()
                ->findOrFail($defect->id);

            Gate::authorize('comment', $locked);

            $comment = new DefectComment([
                'body' => $validated['body'],
            ]);
            $comment->defect()->associate($locked);
            $comment->user()->associate($user);
            $comment->save();
        });

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Comment added successfully.');
    }
}
