<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Grades | Digitech College</title>
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
                <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Academic record</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight">Your grades</h2>
                    <p class="mt-2 max-w-2xl text-white">
                        Review grades released by your teachers and keep track of
                        your academic progress by term.
                    </p>
                </section>
                <section class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                            <i data-lucide="rows-3"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Released subjects</p>
                        <p id="gradeTotal" class="mt-1 text-3xl font-extrabold">0</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <i data-lucide="chart-no-axes-combined"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Current average</p>
                        <p id="gradeAverage" class="mt-1 text-3xl font-extrabold text-emerald-600">—</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                            <i data-lucide="calendar-range"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">School year</p>
                        <p id="gradeYear" class="mt-1 text-xl font-extrabold">—</p>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="graduation-cap"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Released grades</h3>
                                <p class="text-xs text-slate-400">
                                    Only grades marked as published are displayed here.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4 text-left">Subject</th>
                                    <th class="p-4 text-left">Code</th>
                                    <th class="p-4 text-left">Teacher</th>
                                    <th class="p-4 text-left">Semester</th>
                                    <th class="p-4 text-left">School year</th>
                                    <th class="p-4 text-left">Grade</th>
                                    <th class="p-4 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
</body>

</html>