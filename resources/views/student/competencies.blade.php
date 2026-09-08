<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>TVET Competencies | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">TVET progress</p>
                            <h2 class="mt-2 text-3xl font-extrabold sm:text-4xl">Your competency journey</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">See the outcomes recorded by your
                                teacher and
                                understand what to work on next.</p>
                        </div>
                        <span class="text-sm font-semibold text-white backdrop-blur"><i data-lucide="award"
                                class="mr-2 inline h-4 w-4"></i>Assessment record</span>
                    </div>
                </section>
                <section class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300"><i
                                data-lucide="percent"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Overall progress</p>
                        <p id="pct" class="mt-1 text-3xl font-extrabold">0%</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300"><i
                                data-lucide="badge-check"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Competent</p>
                        <p id="competentCount" class="mt-1 text-3xl font-extrabold text-emerald-600">0</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300"><i
                                data-lucide="list-checks"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Still in progress</p>
                        <p id="remainingCount" class="mt-1 text-3xl font-extrabold text-amber-600">0</p>
                    </div>
                </section>
                <section class="card mb-6 p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-extrabold">Completion overview</p>
                            <p class="mt-1 text-xs text-slate-400">Competent outcomes divided by all assigned
                                competencies.</p>
                        </div><span id="progressLabel" class="text-sm font-bold text-emerald-600">0 of 0</span>
                    </div>
                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div id="bar" class="h-full rounded-full bg-emerald-600 progress" style="width: 0%"></div>
                    </div>
                </section>
                <div id="cards" class="grid gap-4 lg:grid-cols-2"></div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
</body>

</html>