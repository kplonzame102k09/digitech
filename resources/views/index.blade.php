<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Digitech College | Integrated Web Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
</head>

<body class="bg-slate-50 text-slate-800">
    <div id="loader" role="status" aria-label="Loading">
        <img src="{{ asset('images/16432.png') }}" alt="" width="160" height="160" class="logo">
    </div>
    <header class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b">
        <nav class="max-w-7xl mx-auto px-4">
            <div class="h-20 flex items-center justify-between">
                <a class="flex items-center gap-3" href="{{ ('index') }}">
                    <img src="{{ asset('images/16432.png') }}"
                        class="w-12 h-12 rounded-xl flex items-center justify-center">
                    <span>
                        <b class="text-lg">DIGITECH</b>
                        <small class="block text-xs text-slate-500">COLLEGE</small>
                    </span>
                </a>
                <div class="hidden md:flex gap-8 text-sm font-medium">
                    <a href="#home" class="text-lg text-green-600">Home</a>
                    <a href="#services" class="text-lg">Services</a>
                    <a href="#about" class="text-lg">About</a>
                    <a href="#contact" class="text-lg">Contact</a>
                </div>
                <a href="{{ route('auth.login') }}"
                    class="hidden md:inline-flex items-center gap-2 bg-green-600 text-white px-5 py-2.5 rounded-lg font-semibold">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    Portal Login
                </a>
                <button id="menu" class="md:hidden">
                    <i data-lucide="menu"></i>
                </button>
            </div>
            <div id="mobile" class="hidden md:hidden border-t py-3 space-y-1">
                <a class="block p-3" href="#services">Services</a>
                <a class="block p-3" href="#about">About</a>
                <a class="block p-3" href="#contact">Contact</a>
                <a class="block p-3 bg-green-600 text-white rounded-lg text-center"
                    href="{{ route('auth.login') }}">Portal Login</a>
            </div>
        </nav>
    </header>
    <main>
        <section id="home" class="pt-20 pb-0 bg-slate-50">
            <div class="relative overflow-hidden">
                <img src="{{ asset('images/digitech-college-banner.jpg') }}"
                    alt="Digitech College — Keeping up with the Changing World" class="auto-h w-full">

                <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-white via-white/50 to-transparent">
                </div>
            </div>
            <div class="max-w-7xl mt-10 mx-auto px-4 grid lg:grid-cols-2 gap-14 items-center">
                <div>
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-100 rounded-full text-green-700 text-sm font-medium">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                        Integrated College Services Portal
                    </span>
                    <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">Your
                        College Services,
                        <span class="text-green-600">All in One Place.</span>
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 max-w-xl leading-relaxed">
                        Access enrollment, document
                        requests, academic grades, TVET competencies, notifications, and role-specific college services
                        through one convenient portal.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-2">
                        <a href="{{ ('index') }}"
                            class="px-5 py-3.5 rounded-xl bg-green-600 text-white font-semibold shadow-lg">
                            Access Portal
                            <i data-lucide="arrow-right" class="inline w-5"></i>
                        </a>
                        <a href="#services" class="px-6 py-3.5 rounded-xl bg-white border font-semibold">
                            Explore Services
                        </a>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-7 text-sm text-slate-500">
                        <span>
                            <i data-lucide="shield-check" class="inline w-4 text-green-600"></i>
                            Role-based access
                        </span>
                        <span>
                            <i data-lucide="smartphone" class="inline w-4 text-blue-600"></i>
                            Mobile friendly
                        </span>
                        <span>
                            <i data-lucide="database" class="inline w-4 text-purple-600"></i>
                            LocalStorage prototype
                        </span>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute -inset-6 bg-green-100/70 rounded-[2rem] blur-2xl"></div>
                    <div class="relative bg-white rounded-2xl shadow p-5">
                        <div class="flex justify-between border-b pb-4">
                            <div class="flex gap-3">
                                <img src="{{ asset('images/16432.png') }}"
                                    class="w-9 h-9 rounded-lg flex items-center justify-center">
                                <div>
                                    <b class="text-sm">Student Portal</b>
                                    <small class="block text-xs text-slate-400">Dashboard preview</small>
                                </div>
                            </div>
                            <i data-lucide="bell" class="text-slate-500"></i>
                        </div>
                        <div class="pt-5">
                            <small class="text-slate-400">Welcome back</small>
                            <h2 class="text-xl font-bold">Kim Philip Lonzame</h2>
                            <small class="text-slate-400">STU-2026-000001</small>
                            <div class="grid grid-cols-2 gap-3 mt-5">
                                <div class="p-4 rounded-xl bg-green-50">
                                    <i data-lucide="clipboard-check" class="text-green-600"></i>
                                    <small class="block text-slate-500 mt-3">Enrollment</small>
                                    <b>Submitted</b>
                                </div>
                                <div class="p-4 rounded-xl bg-amber-50">
                                    <i data-lucide="file-text" class="text-amber-600"></i>
                                    <small class="block text-slate-500 mt-3">Documents</small>
                                    <b>2 Requests</b>
                                </div>
                                <div class="p-4 rounded-xl bg-blue-50">
                                    <i data-lucide="chart-no-axes-combined" class="text-blue-600"></i>
                                    <small class="block text-slate-500 mt-3">Current GPA</small>
                                    <b>1.50</b>
                                </div>
                                <div class="p-4 rounded-xl bg-purple-50">
                                    <i data-lucide="award" class="text-purple-600"></i>
                                    <small class="block text-slate-500 mt-3">Competencies</small>
                                    <b>2 / 4</b>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="services" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4">
                <div class="text-center max-w-2xl mx-auto">
                    <p class="text-sm font-semibold text-green-600 uppercase tracking-wider">College Services</p>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-bold">Everything You Need in One Portal</h2>
                    <p class="mt-4 text-slate-600">Essential academic and administrative workflows in one interface.</p>
                </div>
                <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
                            <i data-lucide="clipboard-list" class="text-green-600"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Online Enrollment</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                            Register, select a strand or track, and
                            submit enrollment information.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
                            <i data-lucide="file-text" class="text-amber-600"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Document Requests</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                            Request Form 137, Good Moral, TOR,
                            Diploma, and track status.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                            <i data-lucide="chart-no-axes-combined" class="text-blue-600"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">Grades & Results</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                            View grades and academic results connected to your account.
                        </p>
                    </article>
                    <article class="p-6 rounded-2xl border hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
                            <i data-lucide="award" class="text-purple-600"></i>
                        </div>
                        <h3 class="mt-5 text-lg font-bold">TVET Competencies</h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed">
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
                    <p class="text-sm font-semibold text-green-600 uppercase tracking-wider">About the Portal</p>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-bold">A More Connected Digitech College</h2>
                    <p class="mt-5 text-slate-600 leading-relaxed">The integrated portal brings enrollment,
                        requirements, documents, grades, competencies, notifications, and administration into a
                        consistent digital environment.</p>
                    <div class="mt-7 space-y-3 text-sm text-slate-600">
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600"></i>
                            Centralized access to
                            college services
                        </p>
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600"></i>
                            Student, teacher, and admin
                            workspaces
                        </p>
                        <p>
                            <i data-lucide="check-circle" class="inline text-green-600"></i>
                            Responsive desktop and
                            mobile design
                        </p>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div class="bg-white border rounded-2xl p-6 shadow-sm">
                        <i data-lucide="graduation-cap" class="text-green-600"></i>
                        <h3 class="mt-4 font-bold">Students</h3>
                        <p class="mt-2 text-sm text-slate-500">
                            Enrollment, requirements, documents, grades,
                            competencies, profile.
                        </p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm"><i data-lucide="presentation"
                            class="text-blue-600"></i>
                        <h3 class="mt-4 font-bold">Teachers</h3>
                        <p class="mt-2 text-sm text-slate-500">Assigned students, grades, competencies, and academic
                            information.</p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm"><i data-lucide="shield-check"
                            class="text-purple-600"></i>
                        <h3 class="mt-4 font-bold">Administrators</h3>
                        <p class="mt-2 text-sm text-slate-500">System-wide management and statistics.</p>
                    </div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm"><i data-lucide="database"
                            class="text-amber-600"></i>
                        <h3 class="mt-4 font-bold">Prototype Data</h3>
                        <p class="mt-2 text-sm text-slate-500">Persistent browser LocalStorage simulation.</p>
                    </div>
                </div>
            </div>
        </section>
        <section class="py-20">
            <div class="max-w-5xl mx-auto px-4">
                <div
                    class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-green-700 via-green-700 to-emerald-800 p-8 sm:p-12 lg:p-14 shadow-2xl shadow-green-900/20">

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
                            class="mx-auto w-20 h-20 flex items-center justify-center rounded-2xl bg-white/10 border border-white/20 backdrop-blur-sm shadow-lg">
                            <img src="{{ asset('images/16432.png') }}" class="w-15 h-15 text-white">
                        </div>

                        <!-- Label -->
                        <div
                            class="mt-6 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/10 text-green-50 text-sm backdrop-blur-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-300"></span>
                            Digitech College Portal
                        </div>

                        <!-- Heading -->
                        <h2 class="mt-5 text-3xl sm:text-4xl font-bold tracking-tight text-white">
                            Ready to Access <span class="text-green-200">Your Portal?</span>
                        </h2>

                        <!-- Description -->
                        <p class="mt-4 text-green-100 text-base sm:text-lg leading-relaxed max-w-xl mx-auto">
                            Sign in to your Digitech College workspace and manage your academic services, documents,
                            enrollment, and more.
                        </p>

                        <!-- Buttons -->
                        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="{{ ('index') }}"
                                class="group inline-flex items-center justify-center gap-2 bg-white text-green-700 px-7 py-3.5 rounded-xl font-semibold shadow-lg shadow-green-950/20 hover:bg-green-50 hover:-translate-y-0.5 transition-all duration-200">
                                Go to Portal
                                <i data-lucide="arrow-right"
                                    class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-200"></i>
                            </a>

                            <a href="#services"
                                class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-semibold text-white border border-white/20 bg-white/5 hover:bg-white/10 backdrop-blur-sm transition-all duration-200">
                                Explore Services
                                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            </a>
                        </div>

                        <!-- Features -->
                        <div
                            class="mt-10 pt-6 border-t border-white/10 flex flex-wrap items-center justify-center gap-x-7 gap-y-3 text-sm text-green-100">
                            <span class="inline-flex items-center gap-2">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-200"></i>
                                Secure access
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i data-lucide="smartphone" class="w-4 h-4 text-green-200"></i>
                                Mobile friendly
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i data-lucide="layers-3" class="w-4 h-4 text-green-200"></i>
                                All services in one place
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </section>
        <section id="contact" class="py-20 bg-white border-t">
            <div class="max-w-7xl mx-auto px-4 text-center">
                <p class="text-sm font-semibold text-green-600 uppercase tracking-wider">Contact</p>
                <h2 class="mt-3 text-3xl font-bold">Need Assistance?</h2>
                <p class="mt-4 text-slate-500">Contact Digitech College for assistance with portal services.</p>
                <div class="mt-10 grid sm:grid-cols-3 gap-8">
                    <div><i data-lucide="map-pin" class="mx-auto text-blue-600"></i><b
                            class="block mt-3">Campus</b><span class="text-sm text-slate-500">Digitech College</span>
                    </div>
                    <div><i data-lucide="mail" class="mx-auto text-green-600"></i><b class="block mt-3">Email</b><span
                            class="text-sm text-slate-500">info@digitechcollege.edu</span></div>
                    <div><i data-lucide="phone" class="mx-auto text-purple-600"></i><b class="block mt-3">Phone</b><span
                            class="text-sm text-slate-500">College Support</span></div>
                </div>
            </div>
        </section>
    </main>
    <footer class="bg-slate-900 text-slate-400">
        <div class="max-w-7xl mx-auto px-4 py-10 flex justify-between flex-wrap gap-4">
            <a class="flex items-center gap-3" href="{{ ('index') }}">
                <img src="{{ asset('images/16432.png') }}"
                    class="w-11 h-11 rounded-xl flex items-center justify-center">
                <span>
                    <b class="text-lg">DIGITECH</b>
                    <small class="block text-xs text-slate-500">COLLEGE</small>
                </span>
            </a>
            <span class="text-sm">© 2026 · Integrated Web Portal · Prototype</span>
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
</body>

</html>