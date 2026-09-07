<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Competency Management | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/custom.css" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('components.sidebar')
    <div class="lg:pl-64">
        @include('components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8 mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">TVET</p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            Competency Records
                        </h2>
                        <p class="mt-2 text-white">
                            Review assessments, evidence, outcomes, and reassessment needs.
                        </p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" data-add-competency class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 
                            text-sm font-semibold text-white hover:bg-green-700">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            Add competency
                        </button>
                        <button type="button" data-export-competencies class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 
                            py-2.5 text-sm font-semibold dark:border-slate-700">
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Export CSV
                        </button>
                    </div>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="card p-5">
                        <p class="text-xs text-slate-500">All records</p>
                        <b id="totalCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-green-600">Competent</p>
                        <b id="competentCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-amber-600">In progress</p>
                        <b id="progressCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-rose-600">Needs reassessment</p>
                        <b id="reassessmentCount" class="mt-2 block text-2xl"></b>
                    </div>
                </div>
                <section class="card mt-6 p-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto] lg:items-center">
                        <input id="q" class="input w-full rounded-xl border px-3 py-3"
                            placeholder="Search by student, competency, qualification, or ID"
                            aria-label="Search competencies" /><select id="statusFilter"
                            class="input rounded-xl border px-3 py-3">
                            <option value="">All statuses</option>
                            <option>Not Started</option>
                            <option>In Progress</option>
                            <option>Competent</option>
                            <option>Not Yet Competent</option>
                        </select>
                        <select id="qualificationFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All qualifications</option>
                        </select>
                        <select id="sortBy" class="input rounded-xl border px-3 py-3">
                            <option value="student">Sort: Student</option>
                            <option value="status">Sort: Status</option>
                            <option value="date">Sort: Assessment date</option>
                            <option value="qualification">Sort: Qualification</option>
                        </select>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                        <span id="resultCount"></span>
                        <span id="selectionCount" class="font-semibold text-green-700"></span>
                        <button type="button" data-bulk-action class="ml-auto hidden rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white 
                            dark:bg-slate-100 dark:text-slate-900">
                            Process selected
                        </button>
                    </div>
                </section>
                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="w-12 p-4">
                                        <input id="selectAll" type="checkbox" aria-label="Select all competency records" />
                                    </th>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Competency</th>
                                    <th class="p-4">Qualification</th>
                                    <th class="p-4">Assessment</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="award" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No competency records found</h3>
                        <p class="mt-1 text-sm text-slate-500">Try a different filter.</p>
                    </div>
                </section>
                <section class="card mt-6 p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800">
                            <i data-lucide="history" class="h-5 w-5"></i>
                        </span>
                        <div>
                            <h3 class="font-bold">Competency audit history</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Recent assessment changes and reassessment actions.
                            </p>
                        </div>
                    </div>
                    <div id="auditRows" class="mt-5 space-y-3"></div>
                    <p id="auditEmpty" class="hidden mt-5 text-sm text-slate-500">
                        No competency actions recorded.
                    </p>
                </section>
            </div>
        </main>
    </div>
    <dialog id="competencyDialog" class="w-[min(650px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="competencyForm" class="card p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600">
                        Competency review
                    </p>
                    <h3 id="competencyTitle" class="mt-1 text-xl font-bold"></h3>
                </div>
                <button type="button" data-close-competency class="rounded-lg p-2 text-slate-400">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <input id="competencyId" type="hidden" />
            <div id="competencyContext" class="mt-5 grid gap-3 sm:grid-cols-2"></div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold">
                    Status
                    <select id="competencyStatus" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option>Not Started</option>
                        <option>In Progress</option>
                        <option>Competent</option>
                        <option>Not Yet Competent</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">
                    Assessment date
                    <input id="assessmentDate" type="date" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Assessor
                    <input id="assessor" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Evidence link
                    <input id="evidence" type="url" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="https://..." />
                </label>
            </div>
            <label class="mt-4 block text-sm font-semibold">
                Assessment remarks
                <textarea id="remarks" rows="4" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"></textarea>
            </label>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-competency class="rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">
                    Cancel
                </button>
                <button type="submit" class="rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white">
                    Save assessment
                </button>
            </div>
        </form>
    </dialog>
    <dialog id="createCompetencyDialog" class="w-[min(650px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="createCompetencyForm" class="card p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600">New competency record</p>
                    <h3 class="mt-1 text-xl font-bold">Assign competency to a student</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Create the record first; the assigned teacher can complete
                        the assessment later.
                    </p>
                </div>
                <button type="button" data-close-create-competency class="rounded-lg p-2 text-slate-400" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold sm:col-span-2">
                    Student
                    <select id="createStudentId" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="">Select a student</option>
                    </select>
                </label>
                <label class="text-sm font-semibold"> 
                    Competency
                    <input id="createCompetency" required maxlength="120" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" 
                        placeholder="e.g. Install computer systems" />
                </label>
                <label class="text-sm font-semibold">
                    Qualification
                    <input id="createQualification" maxlength="120" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                        placeholder="e.g. Computer Systems Servicing NC II" />
                </label>
                <label class="text-sm font-semibold">
                    Initial status
                    <select id="createStatus" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option>Not Started</option>
                        <option>In Progress</option>
                        <option>Competent</option>
                        <option>Not Yet Competent</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">
                    Assessment date 
                    <span class="font-normal text-slate-400">(optional)</span>
                    <input id="createAssessmentDate" type="date" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Assessor 
                    <span class="font-normal text-slate-400">(optional)</span>
                    <input id="createAssessor" maxlength="120" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                        placeholder="Teacher or assessor name" />
                </label>
                <label class="text-sm font-semibold">
                    Evidence link 
                    <span class="font-normal text-slate-400">(optional)</span>
                    <input id="createEvidence" type="url" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                        placeholder="https://..." />
                </label>
            </div>
            <label class="mt-4 block text-sm font-semibold">
                Notes 
                <span class="font-normal text-slate-400">(optional)</span>
                <textarea id="createRemarks" rows="3" maxlength="500" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                    placeholder="Add context for the assigned teacher">
                </textarea>
            </label>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-create-competency class="rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">
                    Cancel
                </button>
                <button type="submit" class="rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700">
                    Create record
                </button>
            </div>
        </form>
    </dialog>
    <template id="competencyRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <input type="checkbox" data-select-record aria-label="Select competency record" />
            </td>
            <td class="p-4">
                <div class="flex items-center gap-3">
                     <img
                        data-student-photo
                        alt="Student profile photo"
                        class="h-9 w-9 rounded-full object-cover"
                    />
                    <span data-student-initials class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50 text-xs font-bold text-green-700"></span>
                    <div>
                        <b data-student-name class="block"></b>
                        <small data-student-id class="text-xs text-slate-400"></small>
                    </div>
                </div>
            </td>
            <td data-competency class="p-4 font-semibold"></td>
            <td data-qualification class="p-4"></td>
            <td data-assessment class="p-4"></td>
            <td class="p-4"><span data-record-status></span></td>
            <td class="p-4 text-right">
                <button type="button" data-action="edit" class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Edit assessment">
                    <i data-lucide="pencil" class="h-4 w-4"></i>
                </button>
            </td>
        </tr>
    </template><template id="auditRowTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex justify-between gap-2">
                <b data-audit-title class="text-sm"></b>
                <time data-audit-date class="text-[11px] text-slate-400"></time>
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