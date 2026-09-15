<?php

namespace App\Http\Controllers;

use App\Models\DefectPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DefectPhotoController extends Controller
{
    public function show(DefectPhoto $photo): StreamedResponse
    {
        $defect = $photo->defect()->firstOrFail();

        Gate::authorize('view', $defect);

        abort_unless($photo->disk === 'defect_photos', 404);

        $disk = Storage::disk('defect_photos');

        abort_unless($disk->exists($photo->path), 404);

        abort_unless(
            in_array($photo->mime_type, [
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true),
            404,
        );

        return $disk->response(
            $photo->path,
            null,
            [
                'Content-Type' => $photo->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }
}
