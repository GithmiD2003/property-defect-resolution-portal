<x-layouts::app :title="$defect->title">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <a href="{{ route('defects.index') }}" class="underline">
            Back to defects
        </a>

        @if (session('success'))
            <div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div>
            <p class="text-sm text-zinc-500">
                DEF-{{ str_pad((string) $defect->id, 6, '0', STR_PAD_LEFT) }}
            </p>
            <h1 class="mt-2 text-2xl font-semibold">{{ $defect->title }}</h1>
            <p class="mt-2">
                {{ $defect->property->name }} · {{ $defect->room->name }}
            </p>
        </div>

        <section class="rounded-xl border border-zinc-300 p-5">
            <h2 class="mb-4 text-lg font-semibold">Report details</h2>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="font-medium">Status</dt>
                    <dd>{{ $defect->status->label() }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Category</dt>
                    <dd>{{ $categories[$defect->category] ?? $defect->category }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Priority</dt>
                    <dd>{{ ucfirst($defect->priority) }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Reported by</dt>
                    <dd>{{ $defect->reporter->name }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Assigned to</dt>
                    <dd>{{ $defect->assignee?->name ?? 'Not assigned' }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Due date</dt>
                    <dd>{{ $defect->due_date?->format('d M Y') ?? 'Not set' }}</dd>
                </div>
            </dl>

            <div class="mt-5 border-t border-zinc-200 pt-4">
                <h3 class="font-medium">Description</h3>
                <p class="mt-2 whitespace-pre-line">{{ $defect->description }}</p>
            </div>

            @can('view', $defect->property)
                <a
                    href="{{ route('properties.show', $defect->property) }}"
                    class="mt-5 inline-block underline"
                >
                    View property
                </a>
            @endcan
        </section>

        @can('assign', $defect)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-4 text-lg font-semibold">
            Assign contractor
        </h2>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($contractors->isEmpty())
            <p>No contractor accounts are available yet.</p>
            <a
                href="{{ route('invitations.index') }}"
                class="mt-3 inline-block underline"
            >
                Invite a contractor
            </a>
        @else
            <form
                method="POST"
                action="{{ route('defects.assignment.update', $defect) }}"
                class="space-y-4"
            >
                @csrf
                @method('PATCH')

                <div>
                    <label for="assigned_to" class="mb-2 block font-medium">
                        Contractor
                    </label>
                    <select
                        id="assigned_to"
                        name="assigned_to"
                        required
                        class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                    >
                        <option value="">Select a contractor</option>
                        @foreach ($contractors as $contractor)
                            <option
                                value="{{ $contractor->id }}"
                                @selected(
                                    (string) old('assigned_to', $defect->assigned_to)
                                    === (string) $contractor->id
                                )
                            >
                                {{ $contractor->name }} ({{ $contractor->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="priority" class="mb-2 block font-medium">
                        Priority
                    </label>
                    <select
                        id="priority"
                        name="priority"
                        required
                        class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                    >
                        @foreach (['low', 'medium', 'high', 'urgent'] as $priority)
                            <option
                                value="{{ $priority }}"
                                @selected(old('priority', $defect->priority) === $priority)
                            >
                                {{ ucfirst($priority) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="due_date" class="mb-2 block font-medium">
                        Due date
                    </label>
                    <input
                        id="due_date"
                        name="due_date"
                        type="date"
                        required
                        min="{{ now()->format('Y-m-d') }}"
                        value="{{ old('due_date', $defect->due_date?->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                    >
                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-blue-700 px-4 py-3 font-medium text-white"
                >
                    Save assignment
                </button>
            </form>
        @endif
    </section>
@endcan

@can('startWork', $defect)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-3 text-lg font-semibold">Start repair work</h2>

        <form method="POST" action="{{ route('defects.start', $defect) }}">
            @csrf
            @method('PATCH')

            <button
                type="submit"
                class="rounded-lg bg-blue-700 px-4 py-3 font-medium text-white"
            >
                Start work
            </button>
        </form>
    </section>
@endcan

@can('markRepaired', $defect)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-4 text-lg font-semibold">Submit completed repair</h2>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg bg-red-50 p-4 text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('defects.repair', $defect) }}"
            enctype="multipart/form-data"
            class="space-y-4"
        >
            @csrf

            <div>
                <label for="repair_notes" class="mb-2 block font-medium">
                    Repair notes
                </label>
                <textarea
                    id="repair_notes"
                    name="repair_notes"
                    rows="5"
                    required
                    maxlength="10000"
                    class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                >{{ old('repair_notes') }}</textarea>
            </div>

            <div>
                <label for="repair_photos" class="mb-2 block font-medium">
                    After-repair photos
                </label>
                <input
                    id="repair_photos"
                    name="photos[]"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    required
                    class="w-full rounded-lg border border-zinc-300 p-3"
                >
                <p class="mt-2 text-sm">
                    Select 1–3 photos together. JPEG, PNG or WebP;
                    maximum 2 MB each and 6000 pixels per side.
                    Reselect photos if validation fails.
                </p>
            </div>

            <button
                type="submit"
                class="rounded-lg bg-blue-700 px-4 py-3 font-medium text-white"
            >
                Mark repaired
            </button>
        </form>
    </section>
@endcan

@if ($defect->repair_notes)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-3 text-lg font-semibold">Latest repair submission</h2>
        <p class="whitespace-pre-line">{{ $defect->repair_notes }}</p>

        <p class="mt-3 text-sm">
            Submitted:
            {{ $defect->repaired_at?->format('d M Y H:i') }}
            ({{ config('app.timezone') }})
        </p>
    </section>
@endif

@if ($defect->verified_at)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-2 text-lg font-semibold">Repair verified</h2>
        <p>
            Verified on {{ $defect->verified_at->format('d M Y H:i') }}
            ({{ config('app.timezone') }}).
        </p>
    </section>
@endif

@if ($defect->reopen_reason)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-2 text-lg font-semibold">Latest reopening reason</h2>
        <p class="whitespace-pre-line">{{ $defect->reopen_reason }}</p>
        <p class="mt-3 text-sm">
            Reopened on {{ $defect->reopened_at?->format('d M Y H:i') }}
            ({{ config('app.timezone') }}).
        </p>
    </section>
@endif

@can('verifyRepair', $defect)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-3 text-lg font-semibold">Verify repair</h2>
        <p class="mb-4">
            Confirm that you have checked the repair and the defect is resolved.
        </p>

        <form method="POST" action="{{ route('defects.verify', $defect) }}">
            @csrf
            @method('PATCH')

            <button
                type="submit"
                class="rounded-lg bg-green-700 px-4 py-3 font-medium text-white"
            >
                Verify repair
            </button>
        </form>
    </section>
@endcan

@can('reopen', $defect)
    <section class="rounded-xl border border-zinc-300 p-5">
        <h2 class="mb-3 text-lg font-semibold">Reopen defect</h2>
        <p class="mb-4">
            If the problem remains or has returned, explain what needs further work.
        </p>

        <form
            method="POST"
            action="{{ route('defects.reopen', $defect) }}"
            class="space-y-4"
        >
            @csrf
            @method('PATCH')

            <div>
                <label for="reopen_reason" class="mb-2 block font-medium">
                    Reason for reopening
                </label>
                <textarea
                    id="reopen_reason"
                    name="reopen_reason"
                    rows="4"
                    required
                    maxlength="5000"
                    class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                >{{ old('reopen_reason') }}</textarea>

                @error('reopen_reason')
                    <p role="alert" class="mt-2 text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="rounded-lg bg-red-700 px-4 py-3 font-medium text-white"
            >
                Reopen defect
            </button>
        </form>
    </section>
@endcan

<section class="space-y-4 rounded-xl border border-zinc-300 p-5">
    <h2 class="text-lg font-semibold">Activity history</h2>

    @forelse ($activities as $activity)
        <article class="rounded-lg border border-zinc-300 p-4">
            <p class="font-medium">{{ $activity->description }}</p>

            <p class="mt-1 text-sm">
                {{ $activity->user?->name ?? 'Deleted user' }}
                &middot;
                {{ $activity->created_at->format('d M Y H:i') }}
                ({{ config('app.timezone') }})
            </p>

            @if ($activity->event === 'assigned')
                <p class="mt-3 text-sm">
                    Priority:
                    {{ ucfirst($activity->metadata['priority'] ?? '') }}
                    &middot;
                    Due: {{ $activity->metadata['due_date'] ?? 'Not set' }}
                </p>
            @endif

            @if ($activity->event === 'repaired')
                <p class="mt-3 whitespace-pre-line">{{ $activity->metadata['repair_notes'] ?? '' }}</p>
            @endif

            @if ($activity->event === 'reopened')
                <p class="mt-3 whitespace-pre-line">{{ $activity->metadata['reason'] ?? '' }}</p>
            @endif
        </article>
    @empty
        <p>No activity has been recorded yet.</p>
    @endforelse

    {{ $activities->links() }}
</section>

<section class="space-y-4 rounded-xl border border-zinc-300 p-5">
    <h2 class="text-lg font-semibold">Comments</h2>

    @can('comment', $defect)
        <form
            method="POST"
            action="{{ route('defects.comments.store', $defect) }}"
            class="space-y-3"
        >
            @csrf

            <div>
                <label for="comment_body" class="mb-2 block font-medium">
                    Add a comment
                </label>

                <textarea
                    id="comment_body"
                    name="body"
                    rows="3"
                    required
                    maxlength="5000"
                    class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
                >{{ old('body') }}</textarea>

                @error('body')
                    <p role="alert" class="mt-2 text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <p class="text-sm">
                Comments are visible to users who have access to this defect.
            </p>

            <button
                type="submit"
                class="rounded-lg bg-blue-700 px-4 py-3 font-medium text-white"
            >
                Add comment
            </button>
        </form>
    @endcan

    <div class="space-y-4">
        @forelse ($comments as $comment)
            <article class="rounded-lg border border-zinc-300 p-4">
                <p class="font-medium">
                    {{ $comment->user?->name ?? 'Deleted user' }}
                </p>

                <p class="mt-1 text-sm">
                    {{ $comment->created_at->format('d M Y H:i') }}
                    ({{ config('app.timezone') }})
                </p>

                <p class="mt-3 whitespace-pre-line">{{ $comment->body }}</p>
            </article>
        @empty
            <p>No comments yet.</p>
        @endforelse
    </div>

    {{ $comments->links() }}
</section> 

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">Photos</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($defect->photos as $photo)
                    <figure class="overflow-hidden rounded-xl border border-zinc-300">
                        <a
                            href="{{ route('defect-photos.show', $photo) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="{{ route('defect-photos.show', $photo) }}"
                                alt="Photo {{ $loop->iteration }} for {{ $defect->title }}"
                                loading="lazy"
                                class="h-56 w-full object-contain"
                            >
                        </a>

                        <figcaption class="border-t border-zinc-200 p-3 text-sm">
                            {{ ucfirst($photo->type) }} repair ·
                            Photo {{ $loop->iteration }}
                        </figcaption>
                    </figure>
                @empty
                    <p class="text-zinc-500">No photos attached.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>