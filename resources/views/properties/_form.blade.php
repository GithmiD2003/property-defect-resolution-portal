@if ($errors->any())
    <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label for="name" class="mb-2 block font-medium">Property name</label>
    <input
        id="name"
        name="name"
        value="{{ old('name', $property->name ?? '') }}"
        required
        maxlength="255"
        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
    >
</div>

<div>
    <label for="address" class="mb-2 block font-medium">Address</label>
    <textarea
        id="address"
        name="address"
        required
        maxlength="2000"
        rows="3"
        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
    >{{ old('address', $property->address ?? '') }}</textarea>
</div>

<div>
    <label for="target_handover_date" class="mb-2 block font-medium">
        Target handover date (optional)
    </label>
    <input
        type="date"
        id="target_handover_date"
        name="target_handover_date"
        value="{{ old('target_handover_date', isset($property) ? $property->target_handover_date?->format('Y-m-d') : '') }}"
        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
    >
</div>

<div>
    <label for="notes" class="mb-2 block font-medium">
        Property notes (visible to linked owners)
    </label>
    <textarea
        id="notes"
        name="notes"
        maxlength="5000"
        rows="4"
        class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
    >{{ old('notes', $property->notes ?? '') }}</textarea>
</div>

<div class="flex flex-wrap items-center gap-4">
    <button
        type="submit"
        class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
    >
        {{ $submitLabel }}
    </button>

    <a
        href="{{ isset($property) ? route('properties.show', $property) : route('properties.index') }}"
        class="underline"
    >
        Cancel
    </a>
</div>