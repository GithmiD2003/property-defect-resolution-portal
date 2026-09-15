<x-layouts::app :title="__('Report defect')">
    <div class="mx-auto w-full max-w-2xl space-y-6">
        <a href="{{ route('properties.show', $property) }}" class="underline">
            Back to property
        </a>

        <div>
            <h1 class="text-2xl font-semibold">Report a defect</h1>
            <p class="mt-2 text-zinc-500">{{ $property->name }}</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($rooms->isEmpty())
            <p class="rounded-lg border border-zinc-300 p-4">
                A manager must add a room to this property before a defect
                can be reported.
            </p>
        @else
            <form
                method="POST"
                action="{{ route('properties.defects.store', $property) }}"
                enctype="multipart/form-data"
                class="space-y-5"
            >
                @csrf

                <div>
                    <label for="title" class="mb-2 block font-medium">
                        Defect title
                    </label>
                    <input
                        id="title"
                        name="title"
                        value="{{ old('title') }}"
                        placeholder="For example: Bathroom tap leaking"
                        required
                        maxlength="255"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                    >
                </div>

                <div>
                    <label for="room_id" class="mb-2 block font-medium">
                        Room
                    </label>
                    <select
                        id="room_id"
                        name="room_id"
                        required
                        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                    >
                        <option value="">Select a room</option>
                        @foreach ($rooms as $room)
                            <option
                                value="{{ $room->id }}"
                                @selected((string) old('room_id') === (string) $room->id)
                            >
                                {{ $room->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category" class="mb-2 block font-medium">
                        Category
                    </label>
                    <select
                        id="category"
                        name="category"
                        required
                        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                    >
                        <option value="">Select a category</option>
                        @foreach ($categories as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('category') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="description" class="mb-2 block font-medium">
                        Description
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        required
                        maxlength="10000"
                        rows="5"
                        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                    >{{ old('description') }}</textarea>
                </div>

                <div>
                    <label for="photos" class="mb-2 block font-medium">
                        Photos (optional)
                    </label>
                    <input
                        type="file"
                        id="photos"
                        name="photos[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        aria-describedby="photo-help"
                        class="block w-full rounded-lg border border-zinc-300 p-3"
                    >
                    <p id="photo-help" class="mt-2 text-sm text-zinc-500">
                        Select up to 3 photos together. JPEG, PNG, or WebP;
                        maximum 2 MB each and 6000 pixels per side.
                        Photos are visible only to users with access to this defect.
                    </p>
                    <p class="mt-1 text-sm text-zinc-500">
                        If validation fails, select your photos again.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
                    >
                        Submit defect report
                    </button>

                    <a href="{{ route('properties.show', $property) }}" class="underline">
                        Cancel
                    </a>
                </div>
            </form>
        @endif
    </div>
</x-layouts::app>