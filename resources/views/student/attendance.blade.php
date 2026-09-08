<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Attendance | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body data-role="student" data-feature="attendance" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.component.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">Student wellbeing</p>
                            <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Attendance</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">
                                Review your attendance history and remarks.
                            </p>
                        </div>
                        <span class="text-sm font-semibold text-white backdrop-blur">
                            <i data-lucide="calendar-check-2" class="mr-2 inline h-4 w-4"></i>
                            Attendance record
                        </span>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800">
                        <div>
                            <h3 class="font-bold">Attendance history</h3>
                            <p class="mt-1 text-xs text-slate-400">Only attendance records shared by the college are
                                shown here.</p>
                        </div><button id="export"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700">Export
                            CSV</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Date</th>
                                    <th class="p-4">Subject / session</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Remarks</th>
                                    <th class="p-4">Action</th>
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