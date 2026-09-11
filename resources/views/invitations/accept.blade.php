<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Accept invitation | Property Defect Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-100 p-6 text-zinc-900">
    <main class="mx-auto my-10 max-w-lg space-y-6 rounded-xl bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-2xl font-semibold">Set up your account</h1>
            <p class="mt-3 break-all">{{ $invitation->email }}</p>
            <p class="text-sm text-zinc-600">
                Role: {{ ucfirst($invitation->role->value) }}
            </p>
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

        <form
            method="POST"
            action="{{ route('invitations.accept.store', $invitation) }}"
            class="space-y-5"
        >
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="name" class="mb-2 block font-medium">Your name</label>
                <input
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    required
                    maxlength="255"
                    class="w-full rounded-lg border border-zinc-300 p-3"
                >
            </div>

            <div>
                <label for="password" class="mb-2 block font-medium">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required
                    minlength="12"
                    class="w-full rounded-lg border border-zinc-300 p-3"
                >
                <p class="mt-2 text-sm text-zinc-600">
                    Use at least 12 characters, including letters and numbers.
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block font-medium">
                    Confirm password
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                    minlength="12"
                    class="w-full rounded-lg border border-zinc-300 p-3"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-blue-700 px-5 py-3 font-medium text-white hover:bg-blue-800"
            >
                Create my account
            </button>
        </form>
    </main>
</body>
</html>