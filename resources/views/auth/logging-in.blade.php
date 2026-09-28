<!doctype html>
<html>

<head>
    @include('partials.meta', ['pageTitle' => 'Signing in | Digitech College'])
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

    <main class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md text-center">
            <a href="{{ url('/') }}" class="inline-flex gap-3 items-center">
                <img src="{{ asset('images/16432.png') }}" class="h-12 w-12 shadow-green-600/20" alt="Digitech College">
                <b class="text-left text-green-900 dark:text-green-200">
                    DIGITECH<br>
                    <small class="text-green-600 tracking-wider dark:text-green-400">COLLEGE</small>
                </b>
            </a>

            <div class="mt-10 flex justify-center">
                <svg class="h-12 w-12 animate-spin text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" role="status" aria-label="Signing you in">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>

            <h1 class="mt-6 text-2xl font-semibold text-slate-900 dark:text-white" aria-live="polite">
                Signing you in&hellip;
            </h1>

            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Verifying your credentials and preparing your portal.
            </p>

            {{-- The completion step is a POST so a prefetched GET can never
                 authenticate anybody. The ref is the enciphered identifier the
                 loading route was opened with. --}}
            <form method="POST" action="{{ route('login.complete') }}" id="login-complete-form" class="hidden">
                @csrf
            </form>

            {{-- Without JavaScript the form never submits on its own, so offer
                 the same action as a real button rather than stranding the
                 user on a permanent spinner. --}}
            <noscript>
                <div class="mt-6">
                    <button type="submit" form="login-complete-form"
                        class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                        Continue
                    </button>
                </div>
            </noscript>
        </div>
    </main>

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

            var form = document.getElementById("login-complete-form");
            if (form) {
                form.submit();
            }
        });
    </script>
</body>

</html>
