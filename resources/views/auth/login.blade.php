<!doctype html>
<html>

<head>
    @include('partials.meta', ['pageTitle' => 'Login | Digitech College'])
    @include('partials.assets')
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
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="icon" href="{{ asset('images/16432.png') }}" type="png">
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <button type="button" data-theme-toggle title="Toggle dark / light mode" aria-label="Toggle dark / light mode"
        class="fixed right-4 top-4 z-50 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white/90 p-2.5 shadow-sm backdrop-blur hover:bg-green-500 hover:text-white dark:border-slate-700 dark:bg-slate-900/90 dark:hover:bg-green-500">
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

        <div class="flex items-start sm:items-center justify-center p-5">
            <div class="w-full max-w-2xl">
                <a href="{{ route('index') }}" title="Back"
                    class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-green-600 hover:underline hover:text-blue-500 dark:hover:text-blue-400 dark:text-green-400">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Back to Home
                </a>
                <div class="p-7 sm:p-9">
                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">Welcome back</p>
                    <h1 class="text-2xl font-bold mt-1">Sign in to your portal</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">Use your User ID to sign in.</p>
                    
                    <form method="POST" action="{{ route('login.submit') }}" class="space-y-4 mt-7">
                        @csrf

                        <label class="block text-sm font-medium">
                            User ID
                            <input id="value" name="user_id" required value="{{ old('user_id') }}"
                                class="input mt-1 w-full rounded border px-3 py-3" placeholder="Enter your User ID">
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
                        <a href="{{ route('account.request') }}" class="text-green-500 hover:underline hover:text-blue-500 dark:hover:text-blue-500"><strong>Request account</strong></a>
                    </p>
                    <div class="my-4 flex items-center gap-3 text-xs uppercase tracking-widest text-slate-400">
                        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
                        <span>or</span>
                        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
                    </div>
                    <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                        Visit the nearest Digitech College branch.
                    </p>
                </div>
                <div class="text-center">
                    <small class="text-red-500 text-xs italic">
                        <strong>NOTE:</strong>
                        <br>
                        
                        IF YOU DO NOT REMEMBER YOUR PASSWORD DO NOT TRY TO GUESS <br>
                        PLEASE CONTACT THE ADMINISTRATOR FOR PASSWORD RESET.
                        
                </small>
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
});
    </script>
    @include('partials.password-toggle')
    @include('partials.gps-consent')
</body>

</html>
