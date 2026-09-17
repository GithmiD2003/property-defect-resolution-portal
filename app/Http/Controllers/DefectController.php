<?php

namespace App\Http\Controllers;

use App\Enums\DefectStatus;
use App\Enums\UserRole;
use App\Models\Defect;
use App\Models\DefectPhoto;
use App\Models\Property;
use App\Models\User;
use App\Queries\VisibleDefects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DefectController extends Controller
{
    private const CATEGORIES = [
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'painting' => 'Painting',
        'tiling' => 'Tiling',
        'carpentry' => 'Carpentry',
        'other' => 'Other',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Defect::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(DefectStatus::class)],
            'priority' => [
                'nullable',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],
            'overdue' => ['nullable', 'boolean'],
        ]);

        $query = VisibleDefects::forUser($user)
            ->with(['property', 'room', 'assignee']);

        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($matches) use ($search) {
                $matches->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('property', function ($properties) use ($search) {
                        $properties->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if ($request->boolean('overdue')) {
            $query->whereDate('due_date', '<', now()->format('Y-m-d'))
                ->whereNotIn('status', [
                    DefectStatus::Repaired->value,
                    DefectStatus::Verified->value,
                ]);
        }

        return view('defects.index', [
            'defects' => $query->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => DefectStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(Property $property): View
    {
        Gate::authorize('create', [Defect::class, $property]);

        return view('defects.create', [
            'property' => $property,
            'rooms' => $property->rooms()->orderBy('name')->get(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(
        Request $request,
        Property $property,
    ): RedirectResponse {
        Gate::authorize('create', [Defect::class, $property]);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => [
                'required',
                Rule::in(array_keys(self::CATEGORIES)),
            ],
            'room_id' => [
                'required',
                'integer',
                Rule::exists('rooms', 'id')
                    ->where('property_id', $property->getKey()),
            ],
            'photos' => ['nullable', 'array', 'max:3'],
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
        $photos = $validated['photos'] ?? [];

        $storedPaths = [];
        $disk = Storage::disk('defect_photos');

        try {
            $defect = DB::transaction(function () use (
                $validated,
                $photos,
                $property,
                $user,
                &$storedPaths,
            ): Defect {
                $defect = new Defect([
                    'title' => $validated['title'],
                    'description' => $validated['description'],
                    'category' => $validated['category'],
                ]);

                $defect->property()->associate($property);
                $defect->room_id = (int) $validated['room_id'];
                $defect->reporter()->associate($user);
                $defect->status = DefectStatus::Reported;
                $defect->priority = 'medium';
                $defect->save();

                foreach ($photos as $upload) {
                    $path = $upload->store(
                        'defects/'.$defect->id,
                        'defect_photos',
                    );

                    if ($path === false) {
                        throw new RuntimeException('Photo storage failed.');
                    }

                    $storedPaths[] = $path;

                    $photo = new DefectPhoto;
                    $photo->defect()->associate($defect);
                    $photo->uploader()->associate($user);
                    $photo->disk = 'defect_photos';
                    $photo->path = $path;
                    $photo->mime_type = $upload->getMimeType()
                        ?? 'application/octet-stream';
                    $photo->size = $upload->getSize();
                    $photo->type = 'before';
                    $photo->save();
                }

                $defect->recordActivity(
                    $user,
                    'reported',
                    'Defect reported.',
                    [
                        'title' => $defect->title,
                        'category' => $defect->category,
                        'room_id' => $defect->room_id,
                    ],
                );

                return $defect;
            });
        } catch (Throwable $exception) {
            // Database rollback does not remove files already written.
            foreach ($storedPaths as $path) {
                try {
                    if (! $disk->delete($path)) {
                        report(new RuntimeException(
                            'Could not clean up a failed defect upload: '.$path,
                        ));
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            report($exception);

            return back()
                ->withErrors([
                    'report' => 'The report could not be saved. Please try again and reselect your photos.',
                ])
                ->withInput($request->only(
                    'title',
                    'description',
                    'category',
                    'room_id',
                ));
        }

        return redirect()
            ->route('defects.show', $defect)
            ->with('success', 'Defect reported successfully.');
    }

    public function show(Defect $defect): View
    {
        Gate::authorize('view', $defect);

        $defect->load([
            'property',
            'room',
            'reporter',
            'assignee',
            'photos',
        ]);

        $contractors = Gate::allows('assign', $defect)
    ? User::query()
        ->where('role', UserRole::Contractor->value)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'email'])
    : collect();

        return view('defects.show', [
            'defect' => $defect,
            'categories' => self::CATEGORIES,
            'contractors' => $contractors,

            'activities' => $defect->activities()
                ->with('user')
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'activity_page'),

            'comments' => $defect->comments()
                ->with('user')
                ->orderByDesc('id')
                ->paginate(10, ['*'], 'comments_page'),
        ]);
    }
}
