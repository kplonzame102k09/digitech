<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Grade Management | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">
                            Academic Records
                        </p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            Grade Management
                        </h2>
                        <p class="mt-2 text-white">
                            One grade record per subject per semester. Term grades compute the final weighted grade, general average, and CHED GWA.
                        </p>
                    </div>
                </section>
                <div class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                    <label class="relative min-w-0 flex-1">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-slate-400"></i>
                        <input id="searchInput" type="search" placeholder="Search student, ID, or teacher"
                            class="input w-full rounded-xl border bg-transparent py-3 pl-10 pr-4" />
                    </label>
                    <select id="sortBy" aria-label="Sort grades"
                        class="input w-auto rounded-xl border px-3 py-3 text-sm font-semibold">
                        <option value="name">Name (A–Z)</option>
                        <option value="nameDesc">Name (Z–A)</option>
                        <option value="id">Student ID</option>
                        <option value="gwa">GWA (best first)</option>
                        <option value="gwaDesc">GWA (weakest first)</option>
                    </select>
                    <select id="semesterFilter" aria-label="Semester filter"
                        class="input w-auto rounded-xl border px-3 py-3 text-sm font-semibold">
                        <option value="1st Semester">1st Semester</option>
                        <option value="2nd Semester">2nd Semester</option>
                        <option value="">All semesters (future)</option>
                    </select>
                    <button type="button" data-export-grades
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-700">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Export CSV
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="card p-5">
                        <p class="text-xs text-slate-500">Students with grades</p>
                        <b id="studentCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-green-600">General average</p>
                        <b id="generalAverage" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-blue-600">GWA (CHED)</p>
                        <b id="gwaCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-amber-600">Published subjects</p>
                        <b id="publishedCount" class="mt-2 block text-2xl"></b>
                    </div>
                </div>
                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Student ID</th>
                                    <th class="p-4">Teacher</th>
                                    <th class="p-4">GWA</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="chart-no-axes-combined" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No grades found</h3>
                        <p class="mt-1 text-sm text-slate-500">Grades for the selected semester appear here.</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <dialog id="gradesModal"
        class="w-[min(880px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="card p-6 dark:text-white">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img data-grade-photo alt="Student profile photo"
                        class="hidden h-14 w-14 rounded-full object-cover" />
                    <span data-grade-initials
                        class="hidden h-14 w-14 items-center justify-center rounded-full bg-green-50 text-lg font-bold text-green-700"></span>
                    <div>
                        <h3 data-grade-student class="text-xl font-bold"></h3>
                        <p data-grade-student-id class="mt-0.5 text-sm text-slate-400"></p>
                        <p data-grade-class class="mt-0.5 text-xs text-slate-400"></p>
                    </div>
                </div>
                <button type="button" data-close-grades class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">School year</label>
                <select id="modalYear" class="input rounded-xl border px-3 py-2 text-sm font-semibold"></select>
                <label class="ml-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Semester</label>
                <select id="modalSemester" class="input rounded-xl border px-3 py-2 text-sm font-semibold">
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                </select>
            </div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="p-3">Subject</th>
                            <th class="p-3">Units</th>
                            <th class="p-3">Prelim</th>
                            <th class="p-3">Midterm</th>
                            <th class="p-3">Finals</th>
                            <th class="p-3">Final Grade</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="modalGradeRows"></tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <button type="button" data-add-subject
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold dark:border-slate-700">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                    Add subject grade
                </button>
                <div class="grid grid-cols-3 gap-3 text-right">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                        <p class="text-[11px] text-slate-400">Total units</p>
                        <p id="modalUnits" class="font-bold">—</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                        <p class="text-[11px] text-slate-400">General average</p>
                        <p id="modalAverage" class="font-bold text-green-600">—</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                        <p class="text-[11px] text-slate-400">GWA (CHED)</p>
                        <p id="modalGwa" class="font-bold text-blue-600">—</p>
                    </div>
                </div>
            </div>
            <div id="annualSummary" class="mt-4 hidden rounded-xl bg-purple-50 p-4 dark:bg-purple-950/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-purple-600 dark:text-purple-300">Annual summary</p>
                <div class="mt-2 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div>
                        <p class="text-[11px] text-slate-400 dark:text-purple-300/70">1st sem GWA</p>
                        <p data-annual-first class="font-bold"></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 dark:text-purple-300/70">2nd sem GWA</p>
                        <p data-annual-second class="font-bold"></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 dark:text-purple-300/70">Annual average</p>
                        <p data-annual-average class="font-bold"></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 dark:text-purple-300/70">Annual GWA</p>
                        <p data-annual-gwa class="font-bold"></p>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-grades
                    class="rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">
                    Close
                </button>
                <button type="button" data-save-grades
                    class="rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700">
                    Save grades
                </button>
            </div>
        </div>
    </dialog>
    <template id="studentRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <img data-student-photo alt="Student profile photo"
                        class="hidden h-9 w-9 rounded-full object-cover" />
                    <span data-student-initials
                        class="hidden h-9 w-9 items-center justify-center rounded-full bg-green-50 text-xs font-bold text-green-700"></span>
                    <b data-student-name class="block"></b>
                </div>
            </td>
            <td data-student-id class="p-4 font-mono text-xs text-slate-500"></td>
            <td data-student-teacher class="p-4 text-slate-600 dark:text-slate-300"></td>
            <td data-student-gwa class="p-4 font-bold text-blue-600"></td>
            <td class="p-4 text-right">
                <button type="button" data-action="view"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600 dark:bg-slate-100 dark:text-slate-900">
                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                    View
                </button>
            </td>
        </tr>
    </template>
    <template id="modalGradeRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-2">
                <input data-row-subject class="input w-40 rounded-lg border px-2 py-1.5 text-sm font-semibold" />
            </td>
            <td class="p-2">
                <input data-row-units type="number" min="0" max="20" step="0.25"
                    class="input w-20 rounded-lg border px-2 py-1.5 text-sm" />
            </td>
            <td class="p-2">
                <input data-row-prelim type="number" min="0" max="100" step="0.01"
                    class="input w-20 rounded-lg border px-2 py-1.5 text-sm" />
            </td>
            <td class="p-2">
                <input data-row-midterm type="number" min="0" max="100" step="0.01"
                    class="input w-20 rounded-lg border px-2 py-1.5 text-sm" />
            </td>
            <td class="p-2">
                <input data-row-finals type="number" min="0" max="100" step="0.01"
                    class="input w-20 rounded-lg border px-2 py-1.5 text-sm" />
            </td>
            <td data-row-final class="p-2 font-bold"></td>
            <td class="p-2 text-right">
                <div class="flex items-center justify-end gap-2">
                    <select data-row-published class="input w-28 rounded-lg border px-2 py-1.5 text-xs"
                        aria-label="Publication state">
                        <option value="false">Unpublished</option>
                        <option value="true">Published</option>
                    </select>
                    <button type="button" data-row-remove title="Remove grade"
                        class="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950">
                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
            </td>
        </tr>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/grades.js'])
</body>

</html>