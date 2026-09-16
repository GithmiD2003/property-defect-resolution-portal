<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto w-full max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <p class="mt-2 text-sm">
                    Track the defects you have access to.
                </p>
            </div>

            <a
                href="{{ route('defects.index') }}"
                class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white"
            >
                View all defects
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <section class="rounded-xl border border-zinc-300 p-5">
                <h2 class="text-sm font-medium">Total defects</h2>
                <p class="mt-3 text-3xl font-semibold">{{ $total }}</p>
            </section>

            <section class="rounded-xl border border-zinc-300 p-5">
                <h2 class="text-sm font-medium">Overdue repairs</h2>
                <p class="mt-3 text-3xl font-semibold">{{ $overdueCount }}</p>
            </section>

            @foreach ($statuses as $status)
                <section class="rounded-xl border border-zinc-300 p-5">
                    <h2 class="text-sm font-medium">
                        {{ $status->label() }}
                    </h2>
                    <p class="mt-3 text-3xl font-semibold">
                        {{ $counts[$status->value] }}
                    </p>
                </section>
            @endforeach
        </div>

        <section class="space-y-4 rounded-xl border border-zinc-300 p-5">
            <div>
                <h2 class="text-lg font-semibold">Overdue repairs</h2>
                <p class="mt-1 text-sm">
                    Past their due date and still awaiting repair.
                    Repaired and verified defects are excluded.
                </p>
            </div>

            @forelse ($overdueDefects as $defect)
                <article class="rounded-lg border border-zinc-300 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a
                            href="{{ route('defects.show', $defect) }}"
                            class="font-semibold underline"
                        >
                            {{ $defect->title }}
                        </a>

                        <span class="text-sm">
                            {{ $defect->status->label() }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm">{{ $defect->property->name }}</p>

                    <p class="mt-2 text-sm">
                        Due: {{ $defect->due_date?->format('d M Y') }}
                        &middot;
                        {{ $defect->assignee?->name ?? 'Not assigned' }}
                    </p>
                </article>
            @empty
                <p>No overdue repairs.</p>
            @endforelse

            @if ($overdueCount > 5)
                <p class="text-sm">Showing the five earliest overdue repairs.</p>
            @endif
        </section>

        <section class="space-y-4 rounded-xl border border-zinc-300 p-5">
            <h2 class="text-lg font-semibold">Recently reported</h2>

            @forelse ($recentDefects as $defect)
                <article class="rounded-lg border border-zinc-300 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a
                            href="{{ route('defects.show', $defect) }}"
                            class="font-semibold underline"
                        >
                            {{ $defect->title }}
                        </a>

                        <span class="text-sm">
                            {{ $defect->status->label() }}
                        </span>
                    </div>

                    <p class="mt-2 text-sm">{{ $defect->property->name }}</p>

                    <p class="mt-2 text-sm">
                        Priority: {{ ucfirst($defect->priority) }}
                        &middot;
                        {{ $defect->assignee?->name ?? 'Not assigned' }}
                    </p>
                </article>
            @empty
                <p>No defects are available for your account yet.</p>
            @endforelse
        </section>
    </div>
</x-layouts::app>