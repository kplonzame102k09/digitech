<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Attendance | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-parent-page="attendance" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
   @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-blue-600">Student wellbeing</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Attendance</h2>
                    <p class="mt-2 text-white">
                        Monitor attendance records for your connected children.
                    </p>
                </section>
                <div id="attendanceFilters" class="mb-6 flex flex-wrap items-center gap-2"></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Present</p>
                        <p id="presentCount" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Recorded attendance</p>
                    </div>
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Late</p>
                        <p id="lateCount" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Recorded attendance</p>
                    </div>
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Absent</p>
                        <p id="absentCount" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Recorded attendance</p>
                    </div>
                </div>
                <section id="attendanceTable" class="card mt-6 hidden overflow-hidden">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                        <h3 class="font-bold">Attendance history</h3>
                        <p class="mt-1 text-xs text-slate-400">
                            Only attendance records shared by the college are shown here.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="p-4">Date</th>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Subject / session</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceRows"></tbody>
                        </table>
                    </div>
                </section>
                <div id="attendanceEmpty" class="mt-6 hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No attendance records yet</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        Attendance records will appear here when they are shared by the
                        school.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="studentFilterTemplate">
        <a data-filter-link href="{{ ('#') }}" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
            <span data-filter-label></span>
        </a>
    </template>
    <template id="attendanceRowTemplate">
        <tr class="border-t border-slate-200 dark:border-slate-800">
            <td data-attendance-date class="whitespace-nowrap p-4"></td>
            <td data-attendance-student class="p-4 font-semibold"></td>
            <td data-attendance-subject class="p-4"></td>
            <td class="p-4">
                <span data-attendance-status></span>
            </td>
            <td data-attendance-remarks class="p-4 text-slate-500 dark:text-slate-400"></td>
        </tr>
    </template>
@include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>

</html>