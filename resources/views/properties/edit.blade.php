<<x-layouts::app :title="__('Edit property')">
    <div class="mx-auto w-full max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold">Edit property</h1>

        <form
            method="POST"
            action="{{ route('properties.update', $property) }}"
            class="space-y-6"
        >
            @csrf
            @method('PATCH')

            @include('properties._form', [
                'submitLabel' => 'Save changes',
            ])
        </form>
    </div>
</x-layouts::app>