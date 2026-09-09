<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">
    <div class="min-h-screen flex items-center justify-center p-5">
        <div class="w-full max-w-md">
            <div class="card p-7 sm:p-9 shadow-xl">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/16432.png') }}" class="w-12 h-12 rounded-xl" alt="Digitech College logo">
                    <div>
                        <b>DIGITECH</b>
                        <small class="block text-[10px] tracking-widest text-slate-400">COLLEGE PORTAL</small>
                    </div>
                </div>
                <p class="mt-6 text-sm font-semibold text-green-600">Security notice</p>
                <h1 class="text-2xl font-bold mt-1">Set a new password</h1>
                <p class="text-sm text-slate-500 mt-2">
                    Your account was issued a temporary password. Choose a permanent one before continuing.
                </p>

                <form method="POST" action="{{ route('auth.password.update') }}" class="space-y-4 mt-7">
                    @csrf

                    <label class="block text-sm font-medium">
                        Current password
                        <input name="current_password" type="password" required autocomplete="current-password"
                            class="input mt-1 w-full rounded-xl border px-3 py-3" placeholder="Enter current password">
                    </label>

                    <label class="block text-sm font-medium">
                        New password
                        <input name="password" type="password" required autocomplete="new-password" minlength="12"
                            class="input mt-1 w-full rounded-xl border px-3 py-3" placeholder="12+ characters">
                    </label>

                    <label class="block text-sm font-medium">
                        Confirm new password
                        <input name="password_confirmation" type="password" required autocomplete="new-password"
                            minlength="12"
                            class="input mt-1 w-full rounded-xl border px-3 py-3" placeholder="Repeat new password">
                    </label>

                    @if (session('success'))
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <button class="w-full bg-green-600 hover:bg-green-700 text-white rounded-xl py-3.5 font-semibold">
                        Update password
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>