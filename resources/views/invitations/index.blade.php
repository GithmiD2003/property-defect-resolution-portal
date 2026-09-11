<x-layouts::app :title="__('Invitations')">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <h1 class="text-2xl font-semibold">User invitations</h1>

        @if (session('success'))
            <div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('invitations.store') }}"
            class="space-y-4 rounded-xl border border-zinc-300 p-5"
        >
            @csrf

            <h2 class="text-lg font-semibold">Invite a user</h2>

            <div>
                <label for="email" class="mb-2 block font-medium">
                    Email address
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    maxlength="255"
                    class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                >
            </div>

            <div>
                <label for="role" class="mb-2 block font-medium">Role</label>
                <select
                    id="role"
                    name="role"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                >
                    <option value="owner" @selected(old('role', 'owner') === 'owner')>
                        Property owner
                    </option>
                    <option value="contractor" @selected(old('role') === 'contractor')>
                        Contractor
                    </option>
                </select>
            </div>

            <div>
                <label for="property_id" class="mb-2 block font-medium">
                    Property (optional, owners only)
                </label>
                <select
                    id="property_id"
                    name="property_id"
                    class="w-full rounded-lg border border-zinc-300 bg-transparent p-3"
                >
                    <option value="">No property assigned</option>
                    @foreach ($properties as $property)
                        <option
                            value="{{ $property->id }}"
                            @selected((string) old('property_id') === (string) $property->id)
                        >
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-zinc-500">
                    For contractors, leave “No property assigned” selected.
                </p>
            </div>

            <button
                type="submit"
                class="rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
            >
                Create invitation
            </button>
        </form>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">Invitation history</h2>

            @forelse ($invitations as $invitation)
                <article class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-300 p-4">
                    <div>
                        <p class="break-all font-medium">{{ $invitation->email }}</p>
                        <p class="text-sm">
                            Role: {{ ucfirst($invitation->role->value) }}
                        </p>

                        <p class="mt-1 text-sm text-zinc-500">
                            @if ($invitation->accepted_at)
                                Accepted
                            @elseif ($invitation->revoked_at)
                                Revoked
                            @elseif ($invitation->expires_at->isPast())
                                Expired
                            @else
                                Pending — expires
                                {{ $invitation->expires_at->format('d M Y H:i T') }}
                            @endif
                        </p>
                    </div>

                    @if ($invitation->isPending())
                        <form
                            method="POST"
                            action="{{ route('invitations.destroy', $invitation) }}"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="rounded-lg border border-red-300 px-4 py-2 text-red-600"
                            >
                                Revoke invitation
                            </button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="text-zinc-500">No invitations created yet.</p>
            @endforelse

            {{ $invitations->links() }}
        </section>
    </div>
</x-layouts::app>