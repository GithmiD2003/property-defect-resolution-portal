<<x-layouts::app :title="__('Create property')">
    <div class="mx-auto w-full max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Create property</h1>
            <p class="mt-2 text-zinc-500">
                Add the property details. You can add rooms afterwards.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('properties.store') }}"
            class="space-y-6"
        >
            @csrf

            @include('properties._form', [
                'submitLabel' => 'Create property',
            ])
        </form>
    </div>
</x-layouts::app>