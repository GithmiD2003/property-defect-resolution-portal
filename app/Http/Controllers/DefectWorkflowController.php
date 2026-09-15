<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\DefectPhoto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DefectWorkflowController extends Controller
{
    public function start(Defect $defect): RedirectResponse
    {
        Gate::authorize('startWork', $defect);

        DB::transaction(function () use ($defect): void {
            $locked = Defect::query()
                ->lockForUpdate()
                ->findOrFail($defect->id);

            Gate::authorize('startWork', $locked);

            $locked->status = DefectStatus::InProgress;
            $locked->started_at = now();
            $locked->save();
        });

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Work started successfully.');
    }

    public function repair(
        Request $request,
        Defect $defect,
    ): RedirectResponse {
        Gate::authorize('markRepaired', $defect);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'repair_notes' => ['required', 'string', 'max:10000'],
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=6000,max_height=6000',
            ],
        ]);

        /** @var array<int, UploadedFile> $photos */
        $photos = array_values($validated['photos']);

        $storedPaths = [];
        $disk = Storage::disk('defect_photos');

        try {
            foreach ($photos as $upload) {
                $path = $upload->store(
                    'defects/'.$defect->id,
                    'defect_photos',
                );

                if ($path === false) {
                    throw new RuntimeException('Photo storage failed.');
                }

                $storedPaths[] = $path;
            }

            DB::transaction(function () use (
                $defect,
                $user,
                $validated,
                $photos,
                $storedPaths,
            ): void {
                $locked = Defect::query()
                    ->lockForUpdate()
                    ->findOrFail($defect->id);

                Gate::authorize('markRepaired', $locked);

                foreach ($photos as $index => $upload) {
                    $photo = new DefectPhoto;
                    $photo->defect()->associate($locked);
                    $photo->uploader()->associate($user);
                    $photo->disk = 'defect_photos';
                    $photo->path = $storedPaths[$index];
                    $photo->mime_type = $upload->getMimeType()
                        ?? 'application/octet-stream';
                    $photo->size = $upload->getSize();
                    $photo->type = 'after';
                    $photo->save();
                }

                $locked->repair_notes = $validated['repair_notes'];
                $locked->repaired_at = now();
                $locked->status = DefectStatus::Repaired;
                $locked->save();
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                try {
                    if (! $disk->delete($path)) {
                        report(new RuntimeException(
                            'Could not clean up a failed repair upload: '.$path,
                        ));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            // Keep authorization failures as forbidden responses.
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            report($exception);

            return back()
                ->withErrors([
                    'repair' => 'The repair could not be saved. Please try again and reselect your photos.',
                ])
                ->withInput($request->only('repair_notes'));
        }

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Repair submitted for verification.');
    }
}
