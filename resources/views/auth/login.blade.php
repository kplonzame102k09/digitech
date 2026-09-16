<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script>
        (function () {
            try {
                var t = localStorage.getItem("dg-theme-last");
                if (t !== "dark" && t !== "light") {
                    t = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
                }
                document.documentElement.classList.toggle("dark", t === "dark");
                document.documentElement.style.colorScheme = t;
            } catch (e) {}
        })();
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <button type="button" data-theme-toggle title="Toggle dark / light mode" aria-label="Toggle dark / light mode"
        class="fixed right-4 top-4 z-50 inline-flex items-center justify-center p-2.5 rounded-xl hover:bg-green-500 hover:text-white dark:hover:bg-green-500">
        <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
    </button>
    <div class="min-h-screen grid lg:grid-cols-2">
        <div
            class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-white via-emerald-50 to-green-200 text-slate-800 dark:text-slate-100 dark:from-slate-950 dark:via-emerald-950 dark:to-slate-900 p-12 flex-col justify-between">
            <div class="absolute -top-32 -right-32 w-80 h-80 bg-green-300/30 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-32 w-96 h-96 bg-emerald-200/40 rounded-full blur-3xl"></div>

            <a href="{{ url('/') }}" class="relative z-10 flex gap-3 items-center">
                <img src="{{ asset('images/16432.png') }}"
                    class="w-14 h-14 flex items-center justify-center shadow-green-600/20">
                <!-- <span class="bg-green-600 text-white rounded p-2">
                    <i data-lucide="graduation-cap" class="w-8 h-8"></i>
                </span> -->

                <b class="text-green-900 dark:text-green-200">
                    DIGITECH<br>
                    <small class="text-green-600 tracking-wider dark:text-green-400">COLLEGE</small>
                </b>
            </a>

            <div class="relative z-10 max-w-lg">
                <p class="text-green-600 uppercase text-sm font-semibold tracking-wider dark:text-green-400">
                    Integrated Web Portal
                </p>

                <h1 class="text-5xl font-bold mt-3 leading-tight text-slate-900 dark:text-white">
                    One portal for your
                    <span class="text-green-600 dark:text-green-400">college journey.</span>
                </h1>

                <p class="mt-5 text-slate-600 dark:text-slate-300 leading-relaxed">
                    Enrollment, documents, grades, competencies, and role-specific services
                    — all connected in one convenient portal.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded bg-white/70 border border-green-100 text-sm text-slate-600 dark:text-slate-300 shadow-sm dark:bg-slate-900/70">
                        <i data-lucide="shield-check" class="w-4 text-green-600 dark:text-green-400"></i>
                        Secure Access
                    </span>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded bg-white/70 border border-green-100 text-sm text-slate-600 dark:text-slate-300 shadow-sm dark:bg-slate-900/70">
                        <i data-lucide="smartphone" class="w-4 text-green-600 dark:text-green-400"></i>
                        Mobile Friendly
                    </span>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded bg-white/70 border border-green-100 text-sm text-slate-600 dark:text-slate-300 shadow-sm dark:bg-slate-900/70">
                        <i data-lucide="layers-3" class="w-4 text-green-600 dark:text-green-400"></i>
                        All-in-One
                    </span>
                    <div class="m-10 left-20 fixed inset-0 -z-10 pointer-events-none flex items-center justify-left">
                        <img src="{{ asset('images/16432.png') }}" alt=""
                            class="w-[500px] h-[500px] object-contain opacity-[0.15]">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center p-5">
            <div class="w-full max-w-2xl">
                <div class="p-7 sm:p-9">
                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">Welcome back</p>
                    <h1 class="text-2xl font-bold mt-1">Sign in to your portal</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Use your generated User ID or registered email.</p>
                    <form method="POST" action="{{ route('login.submit') }}" class="space-y-4 mt-7">
                        @csrf

                        <label class="block text-sm font-medium">
                            Role
                            <select id="role" name="role"
                                class="input mt-1 w-full rounded border px-3 py-3 cursor-pointer">
                                <option value="student" @selected(old('role') === 'student')>Student</option>
                                <option value="teacher" @selected(old('role') === 'teacher')>Teacher</option>
                                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                                <option value="parent" @selected(old('role') === 'parent')>Parent</option>
                                <option value="guest" @selected(old('role') === 'guest')>Guest</option>
                            </select>
                        </label>

                        <label class="block text-sm font-medium">
                            User ID
                            <input id="value" name="user_id" required value="{{ old('user_id') }}"
                                class="input mt-1 w-full rounded border px-3 py-3" placeholder="STU-2026-XXXXXX">
                        </label>

                        <label class="block text-sm font-medium">
                            Account Password
                            <input name="password" type="password" required
                                class="input mt-1 w-full rounded border px-3 py-3">
                        </label>


                        @if (session('success'))
                            <div class="rounded bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700 dark:text-emerald-300">{{ session('success') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="rounded bg-red-50 border border-red-200 p-3 text-sm text-red-700 dark:text-red-300 dark:bg-red-950/40">
                                <ul class="list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <button
                            class="w-full bg-green-600 hover:bg-green-700 text-white rounded py-3.5 font-semibold">
                            Sign In
                        </button>
                    </form>
                    <p class="text-center text-sm text-slate-500 dark:text-slate-400 mt-6">
                        No account? 
                        <br>
                        <a href="{{ route('account.request') }}"><strong>Request account</strong></a> 
                        <br>
                        Or visit the nearest Digitech College branch.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function updatePublicThemeIcon(isDark) {
            document.querySelectorAll("[data-theme-icon]").forEach(function (icon) {
                icon.setAttribute("data-lucide", isDark ? "sun" : "moon");
            });
            if (window.lucide) lucide.createIcons();
        }
        function togglePublicTheme() {
            var isDark = document.documentElement.classList.contains("dark");
            var next = isDark ? "light" : "dark";
            try { localStorage.setItem("dg-theme-last", next); } catch (e) {}
            document.documentElement.classList.toggle("dark", next === "dark");
            document.documentElement.style.colorScheme = next;
            updatePublicThemeIcon(next === "dark");
        }
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();
            updatePublicThemeIcon(document.documentElement.classList.contains("dark"));
            document.querySelectorAll("[data-theme-toggle]").forEach(function (btn) {
                btn.addEventListener("click", togglePublicTheme);
            });
            const roleSelect = document.getElementById('role');
            const userIdInput = document.getElementById('value');

            const placeholders = {
                student: 'STU-2026-XXXXXX',
                teacher: 'TCH-2026-XXXXXX',
                admin: 'ADMIN-000001',
                parent: 'PRT-2026-XXXXXX',
                guest: 'GUEST-2026-XXXXX'
            };

            function updateUserIdPlaceholder() {
                userIdInput.placeholder = placeholders[roleSelect.value];
                userIdInput.value = '';
            }

            roleSelect.addEventListener('change', updateUserIdPlaceholder);
            updateUserIdPlaceholder();
});
    </script>
    @include('partials.password-toggle')
</body>

</html>
