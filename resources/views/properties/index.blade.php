<<x-layouts::app :title="__('Properties')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Properties</h1>

            @can('create', \App\Models\Property::class)
                <a
                    href="{{ route('properties.create') }}"
                    class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
                >
                    Create property
                </a>
            @endcan
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($properties as $property)
                <article class="rounded-xl border border-zinc-300 p-5">
                    <h2 class="text-lg font-semibold">
                        <a
                            href="{{ route('properties.show', $property) }}"
                            class="hover:underline"
                        >
                            {{ $property->name }}
                        </a>
                    </h2>

                    <p class="mt-2 whitespace-pre-line text-zinc-500">{{ $property->address }}</p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div>
                            <dt class="inline font-medium">Rooms:</dt>
                            <dd class="inline">{{ $property->rooms_count }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">Target handover:</dt>
                            <dd class="inline">
                                {{ $property->target_handover_date?->format('d M Y') ?? 'Not set' }}
                            </dd>
                        </div>
                    </dl>

                    <a
                        href="{{ route('properties.show', $property) }}"
                        class="mt-5 inline-block font-medium underline"
                    >
                        View property
                    </a>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-zinc-300 p-6">
                    No properties are available for your account yet.
                </p>
            @endforelse
        </div>

        {{ $properties->links() }}
    </div>
</x-layouts::app>