<<x-layouts::app :title="$property->name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <a href="{{ route('properties.index') }}" class="underline">
            Back to properties
        </a>

        @if (session('success'))
            <div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ $property->name }}</h1>

            @can('update', $property)
                <a
                    href="{{ route('properties.edit', $property) }}"
                    class="rounded-lg border border-zinc-300 px-4 py-2"
                >
                    Edit property
                </a>
            @endcan
        </div>

        <section class="space-y-4 rounded-xl border border-zinc-300 p-5">
            <h2 class="text-lg font-semibold">Property details</h2>

            <div>
                <h3 class="font-medium">Address</h3>
                <p class="whitespace-pre-line">{{ $property->address }}</p>
            </div>

            <div>
                <h3 class="font-medium">Target handover</h3>
                <p>
                    {{ $property->target_handover_date?->format('d M Y') ?? 'Not set' }}
                </p>
            </div>

            @if ($property->notes)
                <div>
                    <h3 class="font-medium">Notes</h3>
                    <p class="whitespace-pre-line">{{ $property->notes }}</p>
                </div>
            @endif
        </section>

        <section class="space-y-4 rounded-xl border border-zinc-300 p-5">
            <h2 class="text-lg font-semibold">Rooms</h2>

            <ul class="space-y-2">
                @forelse ($property->rooms as $room)
                    <li class="rounded-lg border border-zinc-200 p-3">
                        {{ $room->name }}
                    </li>
                @empty
                    <li class="text-zinc-500">No rooms added yet.</li>
                @endforelse
            </ul>

            @can('manageRooms', $property)
                <form
                    method="POST"
                    action="{{ route('properties.rooms.store', $property) }}"
                    class="space-y-3 border-t border-zinc-200 pt-4"
                >
                    @csrf

                    <label for="room-name" class="block font-medium">
                        Add a room
                    </label>

                    <input
                        id="room-name"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="For example: Upstairs bathroom"
                        required
                        maxlength="100"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                    >

                    @error('name')
                        <p role="alert" class="text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
                    >
                        Add room
                    </button>
                </form>
            @endcan
        </section>
    </div>
</x-layouts::app>