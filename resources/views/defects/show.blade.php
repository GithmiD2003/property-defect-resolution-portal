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