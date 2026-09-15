<x-layouts::app :title="__('Defects')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Defects</h1>

            @can('viewAny', \App\Models\Property::class)
                <a
                    href="{{ route('properties.index') }}"
                    class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
                >
                    Choose a property to report a defect
                </a>
            @endcan
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($defects as $defect)
                <article class="space-y-3 rounded-xl border border-zinc-300 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm text-zinc-500">
                            DEF-{{ str_pad((string) $defect->id, 6, '0', STR_PAD_LEFT) }}
                        </p>
                        <span class="rounded-full border border-zinc-300 px-3 py-1 text-sm">
                            {{ $defect->status->label() }}
                        </span>
                    </div>

                    <h2 class="text-lg font-semibold">
                        <a href="{{ route('defects.show', $defect) }}" class="hover:underline">
                            {{ $defect->title }}
                        </a>
                    </h2>

                    <p>
                        {{ $defect->property->name }} · {{ $defect->room->name }}
                    </p>

                    <dl class="space-y-1 text-sm">
                        <div>
                            <dt class="inline font-medium">Priority:</dt>
                            <dd class="inline">{{ ucfirst($defect->priority) }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">Assigned to:</dt>
                            <dd class="inline">
                                {{ $defect->assignee?->name ?? 'Not assigned' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">Due:</dt>
                            <dd class="inline">
                                {{ $defect->due_date?->format('d M Y') ?? 'Not set' }}
                            </dd>
                        </div>
                    </dl>

                    <a href="{{ route('defects.show', $defect) }}" class="inline-block underline">
                        View defect
                    </a>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-zinc-300 p-6">
                    No defects are available for your account yet.
                </p>
            @endforelse
        </div>

        {{ $defects->links() }}
    </div>
</x-layouts::app>