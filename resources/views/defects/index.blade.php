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

        <form
    method="GET"
    action="{{ route('defects.index') }}"
    class="space-y-4 rounded-xl border border-zinc-300 p-5"
>
    @if ($errors->any())
        <div role="alert" class="text-red-600">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label for="search" class="mb-2 block font-medium">
                Search
            </label>
            <input
                id="search"
                name="search"
                type="search"
                maxlength="100"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Defect title or property name"
                class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
            >
        </div>

        <div>
            <label for="status" class="mb-2 block font-medium">
                Status
            </label>
            <select
                id="status"
                name="status"
                class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
            >
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected(($filters['status'] ?? '') === $status->value)
                    >
                        {{ $status->label() }}
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
                class="w-full rounded-lg border border-zinc-300 bg-white p-3 text-zinc-900"
            >
                <option value="">All priorities</option>
                @foreach (['low', 'medium', 'high', 'urgent'] as $priority)
                    <option
                        value="{{ $priority }}"
                        @selected(($filters['priority'] ?? '') === $priority)
                    >
                        {{ ucfirst($priority) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="flex items-center gap-2">
        <input
            type="checkbox"
            name="overdue"
            value="1"
            @checked($filters['overdue'] ?? false)
        >
        <span>Overdue repairs only</span>
    </label>

    <div class="flex items-center gap-4">
        <button
            type="submit"
            class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white"
        >
            Apply filters
        </button>

        <a href="{{ route('defects.index') }}" class="underline">
            Clear filters
        </a>
    </div>
</form>

<p class="text-sm">
    {{ $defects->total() }} matching
    {{ $defects->total() === 1 ? 'defect' : 'defects' }}
</p>

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
                    No accessible defects match your filters.
                </p>
            @endforelse
        </div>

        {{ $defects->links() }}
    </div>
</x-layouts::app>