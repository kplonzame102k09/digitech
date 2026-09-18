<!doctype html>
<html>

<head>
    @include('partials.meta', ['pageTitle' => 'Classroom detail | Digitech College'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100" data-classroom-id="{{ $classroomId }}">
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header', ['title' => 'Classroom', 'subtitle' => 'Learner management'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <a href="{{ route('teacher.classrooms') }}"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to classrooms
                </a>
                <section class="mt-3 mb-7 overflow-hidden sm:p-8">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Classroom</p>
                            <h2 id="detailName" class="mt-1 text-3xl font-extrabold tracking-tight">Loading…</h2>
                            <p id="detailMeta" class="mt-1 text-sm"></p>
                        </div>
                        <span id="detailStatus" class="teacher-page-chip"></span>
                    </div>
                    <div class="mt-4 grid gap-4 rounded-2xl p-4 backdrop-blur sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider">9-digit code <span class="font-normal">(fixed subject: <span id="detailSubjectInline"></span>)</span></p>
                            <div class="mt-1 flex items-center gap-2">
                                <code id="detailCode" class="rounded px-3 py-2 font-mono text-lg font-bold tracking-widest text-slate-800 dark:text-slate-100"></code>
                                <button type="button" id="copyCode" class="rounded bg-white/20 px-3 py-2 text-xs font-semibold hover:bg-white/30">Copy</button>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-white/70">Invite link</p>
                            <div class="mt-1 flex items-center gap-2">
                                <input id="detailLink" readonly class="input w-full rounded border border-white/20 px-3 py-2 font-mono text-xs" />
                                <button type="button" id="copyLink" class="rounded px-3 py-2 text-xs font-semibold hover:bg-white/30">Copy</button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" id="regenBoth" class="rounded bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-white/30">Regenerate code + link</button>
                        <button type="button" id="regenCode" class="rounded bg-blue-500 px-3 py-2 text-xs font-semibold text-white hover:bg-white/30">New code only</button>
                        <button type="button" id="archiveToggle" class="rounded bg-red-500 px-3 py-2 text-xs font-semibold text-white hover:bg-white/30">Archive</button>
                    </div>
                    <hr class="mt-10 dark:border-slate-500">
                </section>
                <div id="detailError" role="alert"
                    class="mb-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>

                @include('partials.classroom-video', ['videoRole' => 'teacher'])

                <div class="mb-6 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900 sm:max-w-2xl">
                    <button type="button" data-detail-tab="roster" aria-selected="true"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm font-semibold">Roster</button>
                    <button type="button" data-detail-tab="activities" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400">Activities</button>
                    <button type="button" data-detail-tab="submissions" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400">Submissions</button>
                    <button type="button" data-detail-tab="grades" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400">Grades</button>
                </div>

                <section data-detail-panel="roster" class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">User ID</th>
                                    <th class="p-4">Joined</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="rosterRows"></tbody>
                        </table>
                    </div>
                </section>

                <section data-detail-panel="activities" class="hidden">
                    <div class="card mb-4 p-5">
                        <h3 class="font-extrabold">New activity</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Subject is fixed: <b id="activitySubject"></b>. No per-activity subject.</p>
                        <form id="activityForm" class="mt-3 grid gap-3 md:grid-cols-4">
                            <input id="activityTitle" maxlength="150" required placeholder="Activity title"
                                class="input rounded border px-3 py-2.5 md:col-span-2" />
                            <select id="activityTerm" class="input rounded border px-3 py-2.5">
                                <option>Prelim</option>
                                <option>Midterm</option>
                                <option>Finals</option>
                            </select>
                            <input id="activityDue" type="date" class="input rounded border px-3 py-2.5" />
                            <button class="rounded bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Add</button>
                            <textarea id="activityDesc" rows="2" maxlength="2000" placeholder="Instructions (optional)"
                                class="input rounded border px-3 py-2.5 md:col-span-4"></textarea>
                        </form>
                    </div>
                    <div class="card mb-4 p-5">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Filter by term</label>
                            <select id="activityTermFilter" class="input rounded border px-3 py-2 text-sm">
                                <option value="all">All terms</option>
                                <option>Prelim</option>
                                <option>Midterm</option>
                                <option>Finals</option>
                            </select>
                        </div>
                    </div>
                    <div id="activityList" class="grid gap-4 md:grid-cols-2"></div>
                </section>

                <section data-detail-panel="submissions" class="hidden card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="font-extrabold">Submitted activities & files</h3>
                            <p class="text-xs text-slate-400">View details, score, download, or export.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="submissionFilter" class="input rounded border px-3 py-2 text-sm">
                                <option value="all">All</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Graded">Graded</option>
                                <option value="Returned">Returned</option>
                            </select>
                            <button id="exportSubmissions" type="button" class="rounded border px-4 py-2 text-sm font-semibold">Export CSV</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Activity</th>
                                    <th class="p-4">File</th>
                                    <th class="p-4">Submitted</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Score</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="submissionRows"></tbody>
                        </table>
                    </div>
                </section>

                <section data-detail-panel="grades" class="hidden card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="font-extrabold">Gradebook</h3>
                            <p class="text-xs text-slate-400">One row per student · subject fixed: <b id="gradebookSubject"></b> · final is the mean of prelim / midterm / finals activity averages</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="gradeSemester" class="input rounded border px-3 py-2 text-sm">
                                <option>1st Semester</option>
                                <option>2nd Semester</option>
                            </select>
                            <input id="gradeYear" class="input w-28 rounded border px-3 py-2 text-sm" placeholder="2026-2027" />
                            <button id="loadGradebook" type="button" class="rounded border px-4 py-2 text-sm font-semibold">Load</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Prelim avg</th>
                                    <th class="p-4">Midterm avg</th>
                                    <th class="p-4">Finals avg</th>
                                    <th class="p-4">Prelim</th>
                                    <th class="p-4">Midterm</th>
                                    <th class="p-4">Finals</th>
                                    <th class="p-4">Final</th>
                                    <th class="p-4">GWA</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="gradebookRows"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <dialog id="submissionDialog"
        class="w-[min(720px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="card max-h-[85vh] overflow-y-auto p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img id="subPhoto" src="{{ asset('images/16432.png') }}" alt="Student"
                        class="h-12 w-12 rounded-full object-cover" onerror="this.onerror=null;this.src='{{ asset('images/16432.png') }}'" />
                    <div>
                        <h3 id="subStudent" class="text-lg font-extrabold"></h3>
                        <p id="subMeta" class="text-xs text-slate-400"></p>
                    </div>
                </div>
                <button type="button" data-close-sub class="rounded p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div class="mt-4 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Activity · <span id="subSubject"></span></p>
                <h4 id="subActivity" class="mt-1 font-extrabold"></h4>
                <p id="subNotes" class="mt-1 text-sm text-slate-600 dark:text-slate-300"></p>
            </div>
            <div id="subPreview" class="mt-4 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700"></div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a id="subDownload" href="#" class="rounded bg-slate-900 px-4 py-2 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-900">Download file</a>
                <a id="subOpen" href="#" target="_blank" rel="noopener" class="rounded border px-4 py-2 text-xs font-semibold">Open original</a>
                <span id="subStatus" class="ml-auto text-xs font-semibold text-slate-500 dark:text-slate-400"></span>
            </div>
            <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                <h4 class="font-extrabold">Score submission (0–100)</h4>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <input id="subScore" type="number" min="0" max="100" step="0.01" placeholder="e.g. 92.5"
                        class="input w-full rounded border px-3 py-2.5 sm:max-w-40" />
                    <button id="saveScore" type="button" class="rounded bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save score</button>
                    <button id="returnSubmission" type="button" class="rounded border px-4 py-2.5 text-sm font-semibold">Return</button>
                </div>
                <div id="subGradeInfo" class="mt-3 text-sm text-slate-500 dark:text-slate-400"></div>
            </div>
            <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                <h4 class="font-extrabold">Per-term grade · fixed subject</h4>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Term grades live in the Grades tab — this dialog handles the activity score only.</p>
                <button id="openGradebook" type="button" class="mt-2 rounded bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Open in Grades tab</button>
            </div>
        </div>
    </dialog>

    <div id="modalRoot"></div>
    <script src="{{ asset('js/classroom-video.js') }}"></script>
    @include('partials.portal-scripts', ['portalPage' => 'teacher/classroom-detail.js'])
</body>

</html>
