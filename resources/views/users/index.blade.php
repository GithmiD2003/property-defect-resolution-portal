<x-layouts::app title="Users">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Users</h1>
            <p class="mt-2 text-sm text-zinc-500">
                Manage account access. Deactivation preserves reports,
                comments, and repair history.
            </p>
        </div>

        @if (session('success'))
            <div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <a href="{{ route('invitations.index') }}" class="inline-block underline">
            Invite a user
        </a>

        <div class="space-y-4">
            @forelse ($users as $account)
                <section class="rounded-xl border border-zinc-300 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="break-words font-semibold">
                                {{ $account->name }}
                            </h2>
                            <p class="break-all text-sm">{{ $account->email }}</p>
                            <p class="mt-2 text-sm">
                                {{ ucfirst($account->role->value) }}
                                &middot;
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>

                        @if ($account->id === auth()->id())
                            <span class="text-sm text-zinc-500">
                                Your account
                            </span>
                        @else
                            <form
                                method="POST"
                                action="{{ route('users.status.update', $account) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="{{ $account->is_active ? '0' : '1' }}"
                                >

                                <button
                                    type="submit"
                                    @class([
                                        'rounded-lg px-4 py-2 font-medium text-white',
                                        'bg-red-700 hover:bg-red-800' => $account->is_active,
                                        'bg-blue-700 hover:bg-blue-800' => ! $account->is_active,
                                    ])
                                >
                                    {{ $account->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @empty
                <p>No users found.</p>
            @endforelse
        </div>

        {{ $users->links() }}
    </div>
</x-layouts::app>