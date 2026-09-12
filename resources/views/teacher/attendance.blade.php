<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Attendance management | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-role="teacher" data-feature="attendance" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="teacher-hero mb-7 overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Learner management</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Attendance management</h2>
                            <p class="mt-2 max-w-2xl text-slate">Record attendance for assigned learners.</p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Portal workspace
                        </span>
                    </div>
                </section>
                <section class="teacher-toolbar card mb-6 p-5 sm:p-6">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600">Daily register</p>
                            <h3 class="mt-1 text-xl font-extrabold">Record attendance</h3>
                            <p class="mt-1 text-sm text-slate-500">Create or correct a learner attendance record.</p>
                        </div>
                        <!-- <button id="newAttendance" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                            New record
                        </button> -->
                    </div>
                    <div id="attendanceDialog" class="hidden rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <form id="attendanceForm" class="grid gap-3 md:grid-cols-6">
                            <input id="recordId" type="hidden">
                            <select id="student" class="input rounded-xl border px-3 py-2 md:col-span-2" required></select>
                            <input id="date" type="date" class="input rounded-xl border px-3 py-2" required>
                            <input id="subject" class="input rounded-xl border px-3 py-2" placeholder="Subject/session" required>
                            <select id="status" class="input rounded-xl border px-3 py-2">
                                <option>Present</option>
                                <option>Late</option>
                                <option>Absent</option>
                                <option>Excused</option>
                            </select>
                            <input id="remarks" class="input rounded-xl border px-3 py-2" placeholder="Remarks">
                            <div class="flex justify-end gap-2 md:col-span-6">
                                <button type="button" id="closeAttendance" class="rounded-xl border px-4 py-2">
                                    Cancel
                                </button>
                                <button class="rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white">
                                    Save attendance
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="font-extrabold">Attendance history</h3>
                            <p class="text-xs text-slate-400">Review attendance records and remarks.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="newAttendance" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                                New record
                            </button>
                            <button id="export" class="rounded-xl border px-4 py-2 text-sm font-semibold">
                                Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="px-5 py-3.5">Student</th>
                                    <th class="px-5 py-3.5">Date</th>
                                    <th class="px-5 py-3.5">Subject / session</th>
                                    <th class="px-5 py-3.5">Status</th>
                                    <th class="px-5 py-3.5">Remarks</th>
                                    <th class="px-5 py-3.5 text-right">Action</th>
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
@include('partials.portal-scripts', ['portalPage' => 'workflows.js'])
</body>

</html>