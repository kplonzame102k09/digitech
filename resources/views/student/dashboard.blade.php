<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Student Dashboard | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-student-page="dashboard" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div>
                        <div
                            class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">
                            <span class="h-2 w-2 rounded-full bg-emerald-300"></span>Student workspace
                        </div>
                        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                            Welcome back, 
                            <span id="name"></span>.
                        </h2>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-white">
                            Your academic progress, enrollment tasks, and
                            college updates are all in one place.
                        </p>
                    </div>
                </section>
                <div class="mb-6 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                    <a href="{{ route('student.enrollment') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
                        <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                        Enrollment
                    </a>
                    <a href="{{ route('student.grades') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-700">
                        <i data-lucide="chart-no-axes-combined" class="h-4 w-4"></i>
                        View grades
                    </a>
                </div>
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <i data-lucide="clipboard-check"></i>
                        </span>
                        <p class="mt-4 text-sm font-medium text-slate-500">Enrollment status</p>
                        <p id="enrollmentstatus" class="mt-1 text-2xl font-extrabold">Not started</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                            <i data-lucide="file-text"></i>
                        </span>
                        <p class="mt-4 text-sm font-medium text-slate-500">Document requests</p>
                        <p id="documentrequests" class="mt-1 text-2xl font-extrabold">0</p>
                        <p id="documentrequestsdetail" class="mt-1 text-xs text-slate-400">No requests yet</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                            <i data-lucide="chart-no-axes-combined"></i>
                        </span>
                        <p class="mt-4 text-sm font-medium text-slate-500">Current average</p>
                        <p id="currentgpa" class="mt-1 text-2xl font-extrabold">—</p>
                        <p id="currentgpadetail" class="mt-1 text-xs text-slate-400">No published grades yet</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                            <i data-lucide="award"></i>
                        </span>
                        <p class="mt-4 text-sm font-medium text-slate-500">Competency progress</p>
                        <p id="competencyprogress" class="mt-1 text-2xl font-extrabold">0%</p>
                        <p id="competencydetail" class="mt-1 text-xs text-slate-400">No competency records yet</p>
                    </div>
                </section>
                <section class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                    <div class="card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600">Activity</p>
                                <h3 class="mt-1 text-xl font-extrabold">Recent updates</h3>
                                <p class="mt-1 text-sm text-slate-500">Your latest portal notifications.</p>
                            </div>
                            <button type="button" onclick="APP.showNotifications()" class="inline-flex items-center gap-1 text-sm font-bold text-emerald-700">
                                View all
                                <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                            </button>
                        </div>
                        <div id="activity" class="mt-5 space-y-3"></div>
                    </div>
                    <div class="card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Next step</p>
                                <h3 class="mt-1 text-xl font-extrabold">Enrollment</h3>
                            </div>
                            <i data-lucide="route" class="h-5 w-5 text-blue-500"></i>
                        </div>
                        <div id="enroll" class="mt-5"></div>
                        <a href="{{ route('student.enrollment') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3 t
                                ext-sm font-semibold text-white hover:bg-emerald-700">
                                Open enrollment
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                    </div>
                </section>
                <section class="mt-6">
                    <div class="mb-4">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Shortcuts</p>
                        <h3 class="mt-1 text-xl font-extrabold">Keep moving</h3>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <a href="{{ route('student.requirements') }}"  class="student-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                                <i data-lucide="folder-check"></i>
                            </span>
                            <b class="mt-4 block">Requirements</b>
                            <span class="mt-1 block text-xs text-slate-400">Track submissions</span>
                        </a>
                        <a href="{{ route('student.documents') }}" class="student-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="file-text"></i>
                            </span>
                            <b class="mt-4 block">Documents</b>
                            <span class="mt-1 block text-xs text-slate-400">Request official records</span>
                        </a>
                        <a href="{{ route('student.competencies') }}" class="student-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                                <i data-lucide="award"></i>
                            </span>
                            <b class="mt-4 block">Competencies</b>
                            <span class="mt-1 block text-xs text-slate-400">Review TVET progress</span>
                        </a>
                        <a href="{{ route('student.profile') }}" class="student-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <i data-lucide="user-round"></i>
                            </span>
                            <b class="mt-4 block">Profile</b>
                            <span class="mt-1 block text-xs text-slate-400">Update contact details</span>
                        </a>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'student/student.js'])
</body>

</html>