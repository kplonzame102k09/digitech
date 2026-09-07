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
    <link rel="stylesheet" href="../css/custom.css" />
    @vite([ 'resources/css/app.css', 'resource/js/app.js' ])
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('components.sidebar')
    <div class="lg:pl-64">
        @include('components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8 mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">
                            Academic Records
                        </p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            Grade Management
                        </h2>
                        <p class="mt-2 text-white">
                            Review, correct, and publish academic results across the portal.
                        </p>
                    </div>
                    <button type="button" data-export-grades
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        Export CSV
                    </button>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="card p-5">
                        <p class="text-xs text-slate-500">Visible grades</p>
                        <b id="totalCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-green-600">Average</p>
                        <b id="averageCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-blue-600">Passed</p>
                        <b id="passedCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-amber-600">Unpublished</p>
                        <b id="unpublishedCount" class="mt-2 block text-2xl"></b>
                    </div>
                </div>
                <section class="card mt-6 p-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto] lg:items-center">
                        <input id="q" class="input w-full rounded-xl border px-3 py-3"
                            placeholder="Search by student, subject, teacher, or ID"
                            aria-label="Search grades" /><select id="subjectFilter"
                            class="input rounded-xl border px-3 py-3">
                            <option value="">All subjects</option>
                        </select><select id="remarkFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All results</option>
                            <option>Passed</option>
                            <option>Failed</option>
                        </select><select id="publishFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All publication states</option>
                            <option value="published">Published</option>
                            <option value="unpublished">Unpublished</option>
                        </select>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <select id="sortBy" class="input rounded-lg border px-2.5 py-2">
                            <option value="student">Sort: Student</option>
                            <option value="grade">Sort: Grade</option>
                            <option value="subject">Sort: Subject</option>
                            <option value="status">Sort: Result</option>
                        </select><span id="resultCount"></span><span id="selectionCount"
                            class="font-semibold text-green-700"></span><button type="button" data-bulk-publish
                            class="ml-auto hidden rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                            Publish selected
                        </button>
                    </div>
                </section>
                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="w-12 p-4">
                                        <input id="selectAll" type="checkbox" aria-label="Select all visible grades" />
                                    </th>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Subject</th>
                                    <th class="p-4">Teacher</th>
                                    <th class="p-4">Grade</th>
                                    <th class="p-4">Result</th>
                                    <th class="p-4">Publish</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead> 
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="chart-no-axes-combined" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No grades found</h3>
                        <p class="mt-1 text-sm text-slate-500">Try a different filter.</p>
                    </div>
                </section>
                <section class="card mt-6 p-6">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800"><i
                                data-lucide="history" class="h-5 w-5"></i></span>
                        <div>
                            <h3 class="font-bold">Grade audit history</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Recent grade edits and publication events.
                            </p>
                        </div>
                    </div>
                    <div id="auditRows" class="mt-5 space-y-3"></div>
                    <p id="auditEmpty" class="hidden mt-5 text-sm text-slate-500">
                        No grade actions recorded.
                    </p>
                </section>
            </div>
        </main>
    </div>
    <dialog id="gradeDialog"
        class="w-[min(600px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="gradeForm" class="card p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600">Grade review</p>
                    <h3 id="gradeTitle" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-grade class="rounded-lg p-2 text-slate-400">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <input id="gradeId" type="hidden" />
            <div id="gradeContext" class="mt-5 grid gap-3 sm:grid-cols-2"></div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">Grade (0–100)<input id="gradeValue" type="number" min="0" max="100"
                        step="0.01" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Result<select id="gradeRemark"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option>Passed</option>
                        <option>Failed</option>
                    </select></label><label class="text-sm font-semibold">Term / period<input id="gradeTerm"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Publication<select id="gradePublished"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="false">Unpublished</option>
                        <option value="true">Published</option>
                    </select></label>
            </div>
            <label class="mt-4 block text-sm font-semibold">Notes<textarea id="gradeNotes" rows="3"
                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"></textarea>
            </label>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-grade
                    class="rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">
                    Cancel</button><button type="submit"
                    class="rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white">
                    Save grade
                </button>
            </div>
        </form>
    </dialog>
    <template id="gradeRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <input type="checkbox" data-select-grade aria-label="Select grade" />
            </td>
            <td class="p-4">
                <div class="flex items-center gap-3">

                    <img
                        data-student-photo
                        alt="Student profile photo"
                        class="h-9 w-9 rounded-full object-cover"
                    />
                    <span data-student-initials
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50 text-xs font-bold text-green-700"></span>
                    <div>
                        <b data-student-name class="block"></b><small data-student-id
                            class="text-xs text-slate-400"></small>
                    </div>
                </div>
            </td>
            <td data-subject class="p-4 font-semibold"></td>
            <td data-teacher class="p-4"></td>
            <td data-grade class="p-4 font-bold"></td>
            <td class="p-4"><span data-remark></span></td>
            <td class="p-4"><span data-publish></span></td>
            <td class="p-4 text-right">
                <button type="button" data-action="edit" class="rounded-lg p-2 text-blue-600 hover:bg-blue-50"
                    title="Edit grade">
                    <i data-lucide="pencil" class="h-4 w-4"></i>
                </button>
            </td>
        </tr>
    </template>
    <template id="auditRowTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex justify-between gap-2">
                <b data-audit-title class="text-sm"></b><time data-audit-date class="text-[11px] text-slate-400"></time>
            </div>
            <p data-audit-meta class="mt-1 text-xs text-slate-500"></p>
            <p data-audit-notes class="mt-1 text-xs"></p>
        </div>
    </template>
    <div id="modalRoot"></div>
    <script>
        lucide.createIcons();
    </script>

</body>

</html>