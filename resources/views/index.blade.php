<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Digitech College | Integrated Web Portal</title>
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
    <link rel="icon" href="{{ asset('images/16432.png') }}" type="png">
</head>

<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <div id="loader" role="status" aria-label="Loading">
        <img src="{{ asset('images/16432.png') }}" alt="" width="160" height="160" class="logo">
    </div>
    <header class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b dark:bg-slate-950/90 dark:border-slate-800">
        <nav class="max-w-7xl mx-auto px-4">
            <div class="h-20 flex items-center justify-between">
                <a class="flex items-center gap-3" href="{{ url('/') }}">
                    <img src="{{ asset('images/16432.png') }}"
                        class="w-12 h-12 rounded flex items-center justify-center">
                    <span>
                        <b class="text-lg">DIGITECH</b>
                        <small class="block text-xs text-slate-500 dark:text-slate-400">COLLEGE</small>
                    </span>
                </a>
                <div class="hidden md:flex gap-8 text-sm font-medium">
                    <a href="#home" class="text-lg text-green-600 dark:text-green-400">Home</a>
                    <a href="#services" class="text-lg">Services</a>
                    <a href="#about" class="text-lg">About</a>
                    <a href="#contact" class="text-lg">Contact</a>
                    
                </div>
                <div class="hidden md:flex items-center gap-3">
                    <button type="button" data-theme-toggle title="Toggle dark / light mode" aria-label="Toggle dark / light mode"
                        class="inline-flex items-center justify-center p-2.5 rounded-xl hover:bg-green-500 hover:text-white dark:hover:bg-green-500">
                        <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
                    </button>
                    <a href="{{ route('auth.login') }}"
                        class="inline-flex items-center gap-2 bg-green-600 text-white px-5 py-2.5 rounded font-semibold">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Portal Login
                    </a>
                </div>
                <div class="flex items-center gap-2 md:hidden">
                    <button type="button" data-theme-toggle title="Toggle dark / light mode" aria-label="Toggle dark / light mode"
                        class="inline-flex items-center justify-center rounded border border-slate-200 bg-white p-2.5 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
                    </button>
                    <button id="menu" aria-label="Open menu">
                        <i data-lucide="menu"></i>
                    </button>
                </div>
            </div>
            <div id="mobile" class="hidden md:hidden border-t py-3 space-y-1 dark:border-slate-800">
                <a class="block p-3" href="#services">Services</a>
                <a class="block p-3" href="#about">About</a>
                <a class="block p-3" href="#contact">Contact</a>
                <a class="block p-3" href="{{ route('account.request') }}">Request Account</a>
                <button type="button" data-theme-toggle
                    class="flex w-full items-center justify-center gap-2 rounded border border-slate-200 p-3 text-sm font-semibold dark:border-slate-700">
                    <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
                    Toggle theme
                </button>
                <a class="block p-3 bg-green-600 text-white rounded text-center"
                    href="{{ route('auth.login') }}">Portal Login</a>
            </div>
        </nav>
    </header>
    <main>
        <section id="home" class="pt-20 pb-0 bg-slate-50 dark:bg-slate-950">
            <div class="max-w-7xl mt-10 mx-auto px-4 grid lg:grid-cols-2 gap-14 items-center">
                <div>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-100 rounded-full text-green-700 text-sm font-medium dark:text-green-300 dark:bg-emerald-950/40">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                        Integrated College Services Portal
                    </span>
                    <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">Your
                        College Services,
                        <span class="text-green-600 dark:text-green-400">All in One Place.</span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl leading-relaxed">
                        Access enrollment, document
                        requests, academic grades, TVET competencies, notifications, and role-specific college services
                        through one convenient portal.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-2">
                        <a href="{{ route('auth.login') }}"
                            class="px-5 py-3.5 rounded bg-green-600 text-white font-semibold shadow-lg">
                            Access Portal
                            <i data-lucide="arrow-right" class="inline w-5"></i>
                        </a>
                        <a href="#services" class="px-6 py-3.5 rounded bg-white border font-semibold dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100">
                            Explore Services
                        </a>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-7 text-sm text-slate-500 dark:text-slate-400">
                        <span>
                            <i data-lucide="shield-check" class="inline w-4 text-green-600 dark:text-green-400"></i>
                            Role-based access
                        </span>
                        <span>
                            <i data-lucide="smartphone" class="inline w-4 text-blue-600 dark:text-blue-400"></i>
                            Mobile friendly
                        </span>
                        <span>
                            <i data-lucide="database" class="inline w-4 text-purple-600 dark:text-purple-400"></i>
                            MySQL-backed portal
                        </span>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-6 bg-green-100/70 rounded-[2rem] blur-2xl dark:bg-emerald-950/40"></div>
                    <div class="relative bg-white rounded-2xl shadow p-5 dark:bg-slate-900 dark:border dark:border-slate-800">
                        <div class="flex justify-between border-b pb-4">
                            <div class="flex gap-3">
                                <img src="{{ asset('images/16432.png') }}"
                                    class="w-9 h-9 rounded flex items-center justify-center">
                                <div>
                                    <b class="text-sm">Student Portal</b>
                                    <small class="block text-xs text-slate-400">Dashboard preview</small>
                                </div>
                            </div>
                            <i data-lucide="bell" class="text-slate-500 dark:text-slate-400"></i>
                        </div>
                        <div class="pt-5">
                            <small class="text-slate-400">Welcome back</small>
                            <h2 class="text-xl font-bold">Kim Philip Lonzame</h2>
                            <small class="text-slate-400">STU-2026-000001</small>
                            <div class="grid grid-cols-2 gap-3 mt-5">
                                <div class="p-4 rounded bg-green-50 dark:bg-emerald-950/40">
                                    <i data-lucide="clipboard-check" class="text-green-600 dark:text-green-400"></i>
                                    <small class="block text-slate-500 mt-3 dark:text-slate-400">Enrollment</small>
                                    <b>Submitted</b>
                                </div>
                                <div class="p-4 rounded bg-amber-50 dark:bg-amber-950/40">
                                    <i data-lucide="file-text" class="text-amber-600 dark:text-amber-400"></i>
                                    <small class="block text-slate-500 mt-3 dark:text-slate-400">Documents</small>
                                    <b>2 Requests</b>
                                </div>
                                <div class="p-4 rounded bg-blue-50 dark:bg-blue-950/40">
                                    <i data-lucide="chart-no-axes-combined" class="text-blue-600 dark:text-blue-400"></i>
                                    <small class="block text-slate-500 mt-3 dark:text-slate-400">Current GPA</small>
                                    <b>1.50</b>
                                </div>
                                <div class="p-4 rounded bg-purple-50 dark:bg-purple-950/40">
                                    <i data-lucide="award" class="text-purple-600 dark:text-purple-400"></i>
                                    <small class="block text-slate-500 mt-3 dark:text-slate-400">Competencies</small>
                                    <b>2 / 4</b>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="services" class="py-20 bg-white dark:bg-slate-950">
            <div class="max-w-7xl mx-auto px-4">
                <div class="text-center max-w-2xl mx-auto">
                    <p class="text-sm font-semibold text-green-600 uppercase tracking-wider dark:text-green-400">College Services</p>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-bold">Everything You Need in One Portal</h2>
                    <p class="mt-4 text-slate-600 dark:text-slate-300">Essential academic and administrative workflows in one interface.</p>
                </div>
                <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition dark:border-slate-800 dark:bg-slate-900">
                        <div class="w-12 h-12 rounded bg-green-50 flex items-center justify-center dark:bg-emerald-950/40">
                            <i data-lucide="clipboard-list" class="text-green-600 dark:text-green-400"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Online Enrollment</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed dark:text-slate-400">
                            Register, select a strand or track, and
                            submit enrollment information.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition dark:border-slate-800 dark:bg-slate-900">
                        <div class="w-12 h-12 rounded bg-amber-50 flex items-center justify-center dark:bg-amber-950/40">
                            <i data-lucide="file-text" class="text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Document Requests</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed dark:text-slate-400">
                            Request Form 137, Good Moral, TOR,
                            Diploma, and track status.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition dark:border-slate-800 dark:bg-slate-900">
                        <div class="w-12 h-12 rounded bg-blue-50 flex items-center justify-center dark:bg-blue-950/40">
                            <i data-lucide="chart-no-axes-combined" class="text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Grades & Results</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed dark:text-slate-400">
                            View grades and academic results connected to your account.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition dark:border-slate-800 dark:bg-slate-900">
                        <div class="w-12 h-12 rounded bg-purple-50 flex items-center justify-center dark:bg-purple-950/40">
                            <i data-lucide="award" class="text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">TVET Competencies</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed dark:text-slate-400">
                            Monitor competency assessments and
                            training progress.
                        </p>
                    </article>
                </div>
            </div>
        </section>
        <section id="about" class="py-20">
            <div class="max-w-7xl mx-auto px-4 grid lg:grid-cols-2 gap-14 items-center">
                <div>
                    <p class="text-sm font-semibold text-green-600 uppercase tracking-wider dark:text-green-400">About the Portal</p>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-bold">A More Connected Digitech College</h2>
                    <p class="mt-5 text-slate-600 dark:text-slate-300 leading-relaxed">The integrated portal brings enrollment,
                        requirements, documents, grades, competencies, notifications, and administration into a
                        consistent digital environment.</p>
                    <div class="mt-7 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600 dark:text-green-400"></i>
                            Centralized access to
                            college services
                        </p>
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600 dark:text-green-400"></i>
                            Student, teacher, and admin
                            workspaces
                        </p>
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600 dark:text-green-400"></i>
                            Responsive desktop and
                            mobile design
                        </p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div class="bg-white border rounded-2xl p-6 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                        <i data-lucide="graduation-cap" class="text-green-600 dark:text-green-400"></i>
                        <h3 class="mt-4 font-bold">Students</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Enrollment, requirements, documents, grades,
                            competencies, profile.
                        </p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm dark:bg-slate-900 dark:border-slate-800"><i data-lucide="presentation"
                            class="text-blue-600 dark:text-blue-400"></i>
                        <h3 class="mt-4 font-bold">Teachers</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Assigned students, grades, competencies, and academic
                            information.</p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm dark:bg-slate-900 dark:border-slate-800"><i data-lucide="shield-check"
                            class="text-purple-600 dark:text-purple-400"></i>
                        <h3 class="mt-4 font-bold">Administrators</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">System-wide management and statistics.</p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm dark:bg-slate-900 dark:border-slate-800"><i data-lucide="database"
                            class="text-amber-600 dark:text-amber-400"></i>
                        <h3 class="mt-4 font-bold">Secure Data</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Persistent records stored in the portal database.</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="py-20">
            <div class="max-w-5xl mx-auto px-4">
                <div
                    class="relative overflow-hidden rounded-3xl">

                    <!-- Decorative Background -->
                    <div
                        class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none">
                    </div>
                    <div
                        class="absolute -bottom-32 -left-24 w-80 h-80 rounded-full bg-green-400/20 blur-3xl pointer-events-none">
                    </div>
                    <div class="absolute top-8 right-10 w-16 h-16 rounded-full border border-white/10"></div>
                    <div class="absolute bottom-8 left-10 w-12 h-12 rounded-full border border-white/10"></div>

                    <!-- Content -->
                    <div class="relative z-10 max-w-2xl mx-auto text-center">

                        <!-- Icon -->
                        <div
                            class="mx-auto w-20 h-20 flex items-center justify-center rounded-2xl bg-white/10 backdrop-blur-sm shadow-lg">
                            <img src="{{ asset('images/16432.png') }}" class="w-15 h-15">
                        </div>

                        <!-- Label -->
                        <div
                            class="mt-6 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/10 text-green-50 text-sm backdrop-blur-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-300"></span>
                            Digitech College Portal
                        </div>

                        <!-- Heading -->
                        <h2 class="mt-5 text-3xl sm:text-4xl font-bold tracking-tight">
                            Ready to Access <span class="text-green-500">Your Portal?</span>
                        </h2>

                        <!-- Description -->
                        <p class="mt-4 text-base sm:text-lg leading-relaxed max-w-xl mx-auto">
                            Sign in to your Digitech College workspace and manage your academic services, documents,
                            enrollment, and more.
                        </p>

                        <!-- Buttons -->
                        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="{{ route('auth.login') }}"
                                class="group inline-flex items-center justify-center gap-2 text-green-700 px-7 py-3.5 rounded font-semibold shadow-lg shadow-green-950/20 hover:bg-green-700 hover:text-slate-200 hover:-translate-y-0.5 transition-all duration-200 dark:text-green-300">
                                Go to Portal
                                <i data-lucide="arrow-right"
                                    class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200"></i>
                            </a>

                            <a href="#services"
                                class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded font-semibold border border-white/20 bg-white/5 hover:bg-white/10 backdrop-blur-sm transition-all duration-200">
                                Explore Services
                                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            </a>
                        </div>

                        <!-- Features -->
                        <div
                            class="mt-10 pt-6 border-t border-white/10 flex flex-wrap items-center justify-center gap-x-7 gap-y-3 text-sm text-green-100">
                            <span class="inline-flex items-center gap-2 text-slate-500">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                                Secure access
                            </span>
                            <span class="inline-flex items-center gap-2 text-slate-500">
                                <i data-lucide="smartphone" class="w-4 h-4 text-green-500"></i>
                                Mobile friendly
                            </span>
                            <span class="inline-flex items-center gap-2 text-slate-500">
                                <i data-lucide="layers-3" class="w-4 h-4 text-green-500"></i>
                                All services in one place
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </section>
        <section id="contact" class="py-20 bg-white border-t dark:bg-slate-950 dark:border-slate-800">
            <div class="max-w-7xl mx-auto px-4 text-center">
                <p class="text-sm font-semibold text-green-600 uppercase tracking-wider dark:text-green-400">Request Account</p>
                <h2 class="mt-3 text-3xl font-bold">Need a Portal Account?</h2>
                <p class="mt-4 text-slate-500 dark:text-slate-400">No account yet? Submit a request and our team will review it for you.</p>
                <div class="mt-8 flex justify-center">
                    <a href="{{ route('account.request') }}"
                        class="inline-flex items-center gap-2 px-6 py-3.5 rounded bg-green-600 text-white font-semibold shadow-lg hover:bg-green-700">
                        <i data-lucide="user-plus" class="w-5 h-5"></i>
                        Request Account Here
                    </a>
                </div>
                <div class="mt-10 grid sm:grid-cols-3 gap-8">
                    <div><i data-lucide="map-pin" class="mx-auto text-blue-600 dark:text-blue-400"></i><b
                            class="block mt-3">Campus</b><span class="text-sm text-slate-500 dark:text-slate-400">Digitech College</span>
                    </div>
                    <div><i data-lucide="mail" class="mx-auto text-green-600 dark:text-green-400"></i><b class="block mt-3">Email</b><span
                            class="text-sm text-slate-500 dark:text-slate-400">info@digitechcollege.edu</span></div>
                    <div><i data-lucide="phone" class="mx-auto text-purple-600 dark:text-purple-400"></i><b class="block mt-3">Phone</b><span
                            class="text-sm text-slate-500 dark:text-slate-400">College Support</span></div>
                </div>
            </div>
        </section>
    </main>
    <footer class="bg-slate-900 text-slate-400">
        <div class="max-w-7xl mx-auto px-4 py-10 flex justify-between flex-wrap gap-4">
            <a class="flex items-center gap-3" href="{{ url('/') }}">
                <img src="{{ asset('images/16432.png') }}"
                    class="w-11 h-11 rounded flex items-center justify-center">
                <span>
                    <b class="text-lg">DIGITECH</b>
                    <small class="block text-xs text-slate-500 dark:text-slate-400">COLLEGE</small>
                </span>
            </a>
            <span class="text-sm">© 2026 · Integrated Web Portal · Digitech College</span>
        </div>
    </footer>
    <script>
        (function () {
            const loader = document.getElementById("loader");

            if (!loader) return;

            const MIN_DISPLAY = 1000; // milliseconds
            const start = performance.now();

            loader.classList.add("js-active");

            function hideLoader() {
                const elapsed = performance.now() - start;
                const delay = Math.max(0, MIN_DISPLAY - elapsed);

                setTimeout(() => {
                    loader.classList.add("hidden");

                    // Remove after the fade-out transition
                    setTimeout(() => {
                        loader.remove();
                    }, 300);

                }, delay);
            }

            if (document.readyState === "complete") {
                hideLoader();
            } else {
                window.addEventListener("load", hideLoader, { once: true });
            }
        })();

        document.addEventListener("DOMContentLoaded", () => {
            if (window.location.hash === "#contact") {
                const contact = document.getElementById("contact");

                if (contact) {
                    setTimeout(() => {
                        contact.scrollIntoView({
                            behavior: "smooth",
                            block: "start"
                        });
                    }, 100);
                }
            }
        });   
    </script>
    <script>lucide.createIcons(); menu.onclick = () => mobile.classList.toggle('hidden')</script>
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
        document.querySelectorAll("[data-theme-toggle]").forEach(function (btn) {
            btn.addEventListener("click", togglePublicTheme);
        });
        updatePublicThemeIcon(document.documentElement.classList.contains("dark"));
    </script>
</body>

</html>
