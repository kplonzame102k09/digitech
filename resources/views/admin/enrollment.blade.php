<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Enrollment Management | Digitech College</title>
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
                <section class="student-hero mb-7 p-6 sm:p-8 mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-purple-600">Enrollment</p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">Enrollment Records</h2>
                        <p class="mt-2 max-w-2xl text-white">
                            Review applications, verify requirements, assign students, and
                            keep applicants informed.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" data-export-enrollments class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm 
                            font-semibold dark:border-slate-700">
                            <i data-lucide="download" class="h-4 w-4"></i>
                            Export CSV
                        </button>
                        <button type="button" data-new-enrollment class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold 
                            text-white hover:bg-green-700">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            New record
                        </button>
                    </div>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="card p-5">
                        <p class="text-xs text-slate-500">All applications</p>
                        <b id="totalCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-blue-600">Submitted</p>
                        <b id="submittedCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-amber-600">Under review</p>
                        <b id="reviewCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-green-600">Enrolled</p>
                        <b id="enrolledCount" class="mt-2 block text-2xl"></b>
                    </div>
                    <div class="card p-5">
                        <p class="text-xs text-rose-600">Needs correction</p>
                        <b id="correctionCount" class="mt-2 block text-2xl"></b>
                    </div>
                </div>
                <section class="card mt-6 p-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto_auto] lg:items-center">
                        <label class="sr-only" for="q">Search enrollment records</label>
                        <input id="q" class="input w-full rounded-xl border px-3 py-3" placeholder="Search by student name, ID, program, or record ID" />
                        <select id="statusFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All statuses</option>
                            <option>Draft</option>
                            <option>Submitted</option>
                            <option>Under Review</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                            <option>Needs Correction</option>
                            <option>Enrolled</option>
                        </select>
                        <select id="programFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All programs</option>
                        </select>
                        <select id="yearFilter" class="input rounded-xl border px-3 py-3">
                            <option value="">All school years</option>
                        </select>
                        <select id="sortBy" class="input rounded-xl border px-3 py-3">
                            <option value="updated">Sort: Recent</option>
                            <option value="student">Sort: Student</option>
                            <option value="status">Sort: Status</option>
                            <option value="schoolYear">Sort: School year</option>
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
                                        <input id="selectAll" type="checkbox" aria-label="Select all visible enrollment records" />
                                    </th>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Program</th>
                                    <th class="p-4">School year</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="clipboard-list" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No enrollment records found</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Try a different filter or create a new record.
                        </p>
                    </div>
                </section>
                <section class="card mt-6 p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 
                            dark:text-slate-300">
                            <i data-lucide="history" class="h-5 w-5"></i>
                        </span>
                        <div>
                            <h3 class="font-bold">Review audit history</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Recent status decisions and administrator notes.
                            </p>
                        </div>
                    </div>
                    <div id="auditRows" class="mt-5 space-y-3"></div>
                    <p id="auditEmpty" class="hidden mt-5 text-sm text-slate-500">
                        No enrollment review actions have been recorded.
                    </p>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <dialog id="reviewDialog" class="w-[min(900px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <section class="card max-h-[90vh] overflow-y-auto p-6 dark:text-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-green-600">
                        Enrollment review workspace
                    </p>
                    <h3 id="reviewTitle" class="mt-1 text-xl font-bold"></h3>
                    <p id="reviewSubtitle" class="mt-1 text-xs text-slate-400"></p>
                </div>
                <button type="button" data-close-review class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                <div>
                    <h4 class="text-sm font-bold">Applicant details</h4>
                    <div id="reviewDetails" class="mt-3 grid gap-3 sm:grid-cols-2"></div>
                    <div class="mt-6">
                        <h4 class="text-sm font-bold">Requirements and documents</h4>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Review each linked submission status before making an enrollment decision.</p>
                        <div id="requirementChecks" class="mt-3 space-y-2"></div>
                    </div>
                </div>
                <form id="reviewForm" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                    <input id="reviewId" type="hidden" />
                    <label class="text-sm font-semibold">
                        Decision
                        <select id="reviewStatus" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                            <option>Draft</option>
                            <option>Submitted</option>
                            <option>Under Review</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                            <option>Needs Correction</option>
                            <option>Enrolled</option>
                        </select></label><label class="mt-4 block text-sm font-semibold">Review notes<textarea
                            id="reviewNotes" rows="5" maxlength="500"
                            class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                            placeholder="Add context for the applicant or the next reviewer..."></textarea></label><label
                        id="rejectionReasonField" class="mt-4 hidden text-sm font-semibold">Reason for rejection /
                        correction<textarea id="rejectionReason" rows="3" maxlength="300"
                            class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"></textarea></label><label
                        class="mt-4 block text-sm font-semibold">Assign adviser<select id="assignedTeacher"
                            class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                            <option value="">No adviser assigned</option>
                        </select></label><label class="mt-4 block text-sm font-semibold">Section / class<input
                            id="assignedSection" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                            placeholder="e.g. ICT-1A" /></label>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <button type="submit"
                            class="rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                            Save review decision</button><button type="button" data-close-review
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                            Cancel
                        </button>
                    </div>
                    <p id="reviewFeedback" class="mt-3 text-xs text-slate-500"></p>
                </form>
            </div>
        </section>
    </dialog>
    <dialog id="createDialog"
        class="w-[min(680px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="createForm" class="card max-h-[90vh] overflow-y-auto p-6 dark:text-white">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-600">
                        New enrollment record
                    </p>
                    <h3 class="mt-1 text-xl font-bold">Create enrollment</h3>
                </div>
                <button type="button" data-close-create class="rounded-lg p-2 text-slate-400">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold sm:col-span-2">Student<select id="createStudent" required
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option value="">Choose a student</option>
                    </select></label><label class="text-sm font-semibold">Program type<input id="createProgram" required
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Grade level<input id="createGrade"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Strand<input id="createStrand"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Track<input id="createTrack"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">School year<input id="createYear" required value="2026-2027"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                    class="text-sm font-semibold">Initial status<select id="createStatus"
                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                        <option>Draft</option>
                        <option>Submitted</option>
                        <option>Under Review</option>
                    </select></label>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-create
                    class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                    Cancel</button><button type="submit"
                    class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white">
                    Create record
                </button>
            </div>
            <p id="createFeedback" class="mt-3 text-right text-xs text-slate-500"></p>
        </form>
    </dialog>
    <template id="enrollmentRowTemplate">
        <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="p-4">
                <input type="checkbox" data-select-record aria-label="Select enrollment record" />
            </td>
            <td class="p-4">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center">
                        <img data-student-photo src="{{ asset('images/16432.png') }}"
                            class="absolute inset-0 h-9 w-9 rounded-full object-cover"
                            alt="Student profile photo" />
                        <span data-student-initials
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-green-50 text-xs font-bold text-green-700"></span>
                    </span>
                    <div>
                        <b data-student-name class="block"></b><small data-student-id
                            class="text-xs text-slate-400"></small>
                    </div>
                </div>
            </td>
            <td data-program class="p-4"></td>
            <td data-school-year class="p-4"></td>
            <td class="p-4"><span data-record-status></span></td>
            <td class="p-4">
                <div class="flex justify-end gap-2">
                    <button type="button" data-action="review" class="rounded-lg p-2 text-green-700 hover:bg-green-50"
                        title="Open review">
                        <i data-lucide="file-search" class="h-4 w-4"></i></button><select data-inline-status
                        class="input rounded-lg border px-2 py-1.5 text-xs">
                        <option>Draft</option>
                        <option>Submitted</option>
                        <option>Under Review</option>
                        <option>Approved</option>
                        <option>Rejected</option>
                        <option>Needs Correction</option>
                        <option>Enrolled</option>
                    </select><button type="button" data-action="save"
                        class="rounded-lg p-2 text-blue-600 hover:bg-blue-50" title="Save status">
                        <i data-lucide="save" class="h-4 w-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    </template>
    <template id="auditRowTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <b data-audit-title class="text-sm"></b><time data-audit-date class="text-[11px] text-slate-400"></time>
            </div>
            <p data-audit-meta class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
            <p data-audit-notes class="mt-1 text-xs"></p>
        </div>
    </template>
    @include('partials.portal-scripts', ['portalPage' => 'admin/enrollment.js'])
</body>

</html>
