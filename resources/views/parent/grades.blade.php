<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Grades | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-parent-page="grades" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7">
                    <p class="text-sm font-semibold text-purple-600">
                        Academic progress
                    </p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Grades</h2>
                    <p class="mt-2 text-white">
                        Review published subject grades for each connected student.
                    </p>
                </section>
                <div class="mb-6 flex flex-wrap items-center gap-3">
                    <div id="gradesFilters" class="flex flex-wrap items-center gap-2"></div>
                    <select id="semesterFilter" aria-label="Semester"
                        class="input rounded-xl border px-4 py-2 text-sm font-semibold">
                        <option value="1st Semester">1st Semester</option>
                        <option value="2nd Semester">2nd Semester</option>
                    </select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Graded children</p>
                        <p id="gradeStudents" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400" id="gradeStudentsNote">With published finals</p>
                    </div>
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Subjects with finals</p>
                        <p id="gradeSubjects" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Published term grades</p>
                    </div>
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Overall GWA</p>
                        <p id="gradeGwa" class="mt-1 text-2xl font-bold">—</p>
                        <p class="mt-1 text-xs text-slate-400">Across displayed children</p>
                    </div>
                    <div class="card p-5">
                        <p class="text-sm text-slate-500">Still pending</p>
                        <p id="gradeAttention" class="mt-1 text-2xl font-bold">0</p>
                        <p class="mt-1 text-xs text-slate-400">No published finals yet</p>
                    </div>
                </div>
                <section id="gradesTable" class="card mt-8 hidden overflow-hidden">
                    <div class="flex flex-col gap-2 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                        <div>
                            <h3 class="font-bold">Semestral grades</h3>
                            <p class="mt-1 text-xs text-slate-400">
                                Weighted finals (Prelim 20% · Midterm 30% · Finals 50%). Only published grades are shown.
                            </p>
                        </div>
                        <button type="button" id="exportVisible"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3.5 py-2 text-sm font-semibold hover:border-slate-300 dark:border-slate-700">
                            <i data-lucide="download" class="h-4 w-4"></i>Export CSV
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">ID</th>
                                    <th class="p-4">General average</th>
                                    <th class="p-4 text-right">Details</th>
                                </tr>
                            </thead>
                            <tbody id="gradesRows"></tbody>
                        </table>
                    </div>
                </section>
                <div id="gradesEmpty" class="mt-8 hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No grades available</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        Published grades for your connected children will appear here.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <dialog id="gradesModal"
        class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white text-slate-800 shadow-2xl backdrop:bg-slate-950/60 dark:bg-slate-900 dark:text-slate-100">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="flex items-center gap-4">
                <span data-grade-photo-wrap
                    class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-purple-100 to-blue-100 dark:from-purple-950 dark:to-blue-950">
                    <img data-grade-photo alt="" class="hidden h-full w-full object-cover" />
                    <span data-grade-initials class="text-sm font-extrabold text-purple-700 dark:text-purple-300"></span>
                </span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-purple-600">Semestral grade details</p>
                    <h3 data-grade-student class="mt-1 text-xl font-bold"></h3>
                    <p data-grade-student-id class="mt-0.5 text-xs text-slate-400"></p>
                </div>
            </div>
            <button type="button" data-close-grades class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <select id="modalSemester" aria-label="Semester" class="input w-40 rounded-xl border px-3 py-2 text-sm font-semibold">
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                </select>
                <select id="modalYear" aria-label="School year" class="input w-40 rounded-xl border px-3 py-2 text-sm font-semibold"></select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="p-3">Subject</th>
                            <th class="p-3">Units</th>
                            <th class="p-3">Prelim</th>
                            <th class="p-3">Midterm</th>
                            <th class="p-3">Finals</th>
                            <th class="p-3">Final grade</th>
                            <th class="p-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="modalGradeRows"></tbody>
                </table>
            </div>
            <div id="annualSummary" class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-4 dark:bg-slate-800">
                <div>
                    <p class="text-xs text-slate-400">1st sem GWA</p>
                    <p id="annualFirstGwa" class="mt-1 font-bold">—</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">2nd sem GWA</p>
                    <p id="annualSecondGwa" class="mt-1 font-bold">—</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Annual average</p>
                    <p id="annualAverage" class="mt-1 font-bold">—</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Annual GWA</p>
                    <p id="annualGwa" class="mt-1 font-bold">—</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" data-close-grades class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900">Close</button>
            </div>
        </div>
    </dialog>
    <template id="studentFilterTemplate">
        <a data-filter-link href="{{ ('#') }}"
            class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
            <span data-filter-label></span>
        </a>
    </template>
    <template id="gradesRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-purple-100 to-blue-100 text-xs font-extrabold text-purple-700 dark:from-purple-950 dark:to-blue-950 dark:text-purple-300" data-grade-initials></span>
                    <b data-grade-student class="font-semibold"></b>
                </div>
            </td>
            <td data-grade-student-id class="p-4 font-mono text-xs text-slate-500"></td>
            <td data-grade-average class="p-4 font-bold text-purple-700 dark:text-purple-300"></td>
            <td class="p-4 text-right">
                <button type="button" data-view-grade class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-purple-600 dark:bg-slate-100 dark:text-slate-900">
                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>View
                </button>
            </td>
        </tr>
    </template>
    <template id="gradeRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td data-grade-subject class="p-3 font-semibold"></td>
            <td data-grade-units class="p-3"></td>
            <td data-grade-prelim class="p-3"></td>
            <td data-grade-midterm class="p-3"></td>
            <td data-grade-finals class="p-3"></td>
            <td data-grade-final class="p-3 font-bold"></td>
            <td class="p-3"><span data-grade-remarks></span></td>
        </tr>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>

</html>