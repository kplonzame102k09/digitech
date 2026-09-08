<!doctype html>
<html>

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
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="teacher-hero mb-7 overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Academic records</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Grade workspace</h2>
                            <p class="mt-2 max-w-2xl text-white">
                                Review, update, and publish grades for your assigned learners.
                                Use the publication state to control what students can see.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button id="newGrade" type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                Add grade
                            </button>
                        </div>
                    </div>
                </section>
                <section id="gradeSummary" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></section>
                <div class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="table-2"></i>
                            </span>
                            <div>
                                <h3 class="font-bold">Assigned grade records</h3>
                                <p class="text-xs text-slate-400">
                                    Save each row after reviewing the score and publication state.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Subject</th>
                                    <th class="p-4">Grade</th>
                                    <th class="p-4">Result</th>
                                    <th class="p-4">Term</th>
                                    <th class="p-4">Publish setting</th>
                                    <th class="p-4">Current state</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <dialog id="newGradeDialog" class="w-[min(640px,calc(100%-2rem))] rounded-3xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="newGradeForm" class="card p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Grade entry</p>
                    <h2 class="mt-1 text-2xl font-extrabold">Add grade</h2>
                    <p class="mt-1 text-sm text-slate-500">Create an unpublished grade for one of your assigned learners.</p>
                </div>
                <button type="button" id="closeNewGrade" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold sm:col-span-2">
                    Student
                    <select id="newGradeStudent" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"></select>
                </label>
                <label class="text-sm font-semibold">
                    Subject
                    <input id="newGradeSubject" required maxlength="120" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="e.g. Mathematics" />
                </label>
                <label class="text-sm font-semibold">
                    Subject code
                    <input id="newGradeCode" maxlength="40" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="Optional" />
                </label>
                <label class="text-sm font-semibold">
                    Grade (0–100)
                    <input id="newGradeValue" type="number" min="0" max="100" step="0.01" required class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                </label>
                <label class="text-sm font-semibold">
                    Term / period
                    <input id="newGradeTerm" required maxlength="80" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="e.g. First Semester" />
                </label>
                <label class="text-sm font-semibold">
                    School year
                    <input id="newGradeYear" maxlength="30" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="e.g. 2026–2027" />
                </label>
                <label class="text-sm font-semibold sm:col-span-2">
                    Notes
                    <textarea id="newGradeNotes" rows="3" maxlength="500" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="Optional notes"></textarea>
                </label>
            </div>
            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                New grades are saved as 
                <strong>Unpublished</strong>. 
                Publish them from the grade row after review.
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="cancelNewGrade" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Save grade
                </button>
            </div>
        </form>
    </dialog>
@include('partials.portal-scripts', ['portalPage' => 'teacher/teacher.js'])
</body>

</html>