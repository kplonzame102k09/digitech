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
                <section class="mb-2 overflow-hidden sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Learner management</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Attendance management</h2>
                            <p class="mt-2 max-w-2xl text-slate-500">Record attendance for assigned learners.</p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Portal workspace
                        </span>
                    </div>
                </section>
                <section id="classroomSection" class="card mb-6 p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold">Classrooms</h3>
                            <p class="text-xs text-slate-400">Select a classroom to record attendance for its students.</p>
                        </div>
                        <a href="{{ route('teacher.classrooms') }}" class="rounded border px-4 py-2 text-sm font-semibold">
                            Manage classrooms
                        </a>
                    </div>
                    <div id="classroomError" role="alert" class="mt-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
                    <div id="classroomGrid" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
                    <div id="classroomEmpty" class="hidden py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        No classrooms yet. Create one in Classrooms to start recording attendance.
                    </div>
                </section>
                <section id="classroomDetail" class="hidden">
                <button id="backToClassrooms" type="button" class="mb-4 inline-flex items-center gap-2 rounded border border-slate-200 px-4 py-2 text-sm font-semibold hover:border-emerald-300 dark:border-slate-700">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    All classrooms
                </button>
                <div class="card mb-6 flex flex-col gap-2 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 id="detailName" class="truncate text-xl font-extrabold">Classroom</h3>
                            <p id="detailMeta" class="mt-1 text-xs text-slate-400"></p>
                        </div>
                        <span id="detailCount" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"></span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <label class="relative block w-full max-w-md">
                            <span class="sr-only">Search students</span>
                            <input id="classroomStudentSearch" class="input w-full rounded border py-2.5 pl-4 pr-4" placeholder="Search students in this classroom…" />
                        </label>
                        <label class="ml-auto flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Sessions per day
                            <input id="sessionsPerDay" type="number" min="1" max="20" class="input w-20 rounded border px-2 py-2 text-center" />
                            <button id="saveSessions" type="button" class="rounded bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600 dark:bg-slate-100 dark:text-slate-900">Set</button>
                        </label>
                    </div>
                </div>
                <section class="card mb-6 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="font-extrabold">Daily attendance sheet</h3>
                            <p class="text-xs text-slate-400">Everyone starts ✓ present — tap ✗ for absentees, then save the day once. Works for joined students too.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="sheetSummary" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"></span>
                            <input id="sheetDate" type="date" class="input rounded border px-3 py-2 text-sm" />
                            <button id="saveSheet" type="button" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Save day
                            </button>
                        </div>
                    </div>
                    <div id="sheetRows" class="divide-y divide-slate-100 dark:divide-slate-800"></div>
                </section>
                <section class="mb-6 p-5 sm:p-6">
                    <div id="attendanceDialog" class="hidden rounded border border-emerald-300 bg-emerald-70/80 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <form id="attendanceForm" class="grid gap-3 md:grid-cols-6">
                            <input id="recordId" type="hidden">
                            <select id="student" class="input rounded border px-3 py-2 md:col-span-2" required></select>
                            <input id="date" type="date" class="input rounded border px-3 py-2" required>
                            <input id="subject" class="input rounded border px-3 py-2" placeholder="Subject/session" required readonly title="Auto-filled from the selected classroom">
                            <select id="status" class="input rounded border px-3 py-2">
                                <option>Present</option>
                                <option>Late</option>
                                <option>Absent</option>
                                <option>Excused</option>
                            </select>
                            <input id="remarks" class="input rounded border px-3 py-2" placeholder="Remarks">
                            <div class="flex justify-end gap-2 md:col-span-6">
                                <button type="button" id="closeAttendance" class="rounded border px-4 py-2">
                                    Cancel
                                </button>
                                <button class="rounded bg-emerald-600 px-4 py-2 font-semibold text-white">
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
                            <button id="newAttendance" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                                New record
                            </button>
                            <button id="export" class="rounded border px-4 py-2 text-sm font-semibold">
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
                                    <th class="px-5 py-3.5">Session</th>
                                    <th class="px-5 py-3.5">Status</th>
                                    <th class="px-5 py-3.5">Remarks</th>
                                    <th class="px-5 py-3.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </section>
                </section><!-- /#classroomDetail -->
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'workflows.js'])
</body>

</html>