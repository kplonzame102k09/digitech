<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Parent Dashboard | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body data-parent-page="dashboard" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="family-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-100">
                                <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                                Family workspace
                            </div>
                            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                                Welcome, 
                                <span data-welcome-name>Parent</span>.
                            </h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">
                                Stay connected to your children’s enrollment,
                                grades, attendance, documents, and college updates.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('parent.children') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-cyan-800 underline hover:text-blue-300">
                                <i data-lucide="users-round" class="h-4 w-4"></i>
                                View children
                            </a>
                            <button type="button" data-notifications
                                class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur hover:bg-white/25">
                                <i data-lucide="bell" class="h-4 w-4"></i>
                                Updates
                            </button>
                        </div>
                    </div>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-300">
                            <i data-lucide="users-round"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Linked children</p>
                        <p id="linkedChildrenCount" class="mt-1 text-3xl font-extrabold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Student records connected</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <i data-lucide="clipboard-check"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Active enrollments</p>
                        <p id="activeEnrollmentsCount" class="mt-1 text-3xl font-extrabold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Submitted or enrolled</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                            <i data-lucide="chart-no-axes-combined"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Grade average</p>
                        <p id="gradeAverage" class="mt-1 text-3xl font-extrabold">—</p>
                        <p id="gradeAverageDetail" class="mt-1 text-xs text-slate-400">No published grades yet</p>
                    </div>
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                            <i data-lucide="bell-ring"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Unread updates</p>
                        <p id="unreadUpdates" class="mt-1 text-3xl font-extrabold">0</p>
                        <p id="unreadUpdatesDetail" class="mt-1 text-xs text-slate-400">You are all caught up</p>
                    </div>
                </div>
                <div class="mt-6 grid gap-6 lg:grid-cols-3">
                    <section class="card p-6 lg:col-span-2">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold">Children overview</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    A quick view of each connected student account.
                                </p>
                            </div>
                            <a href="{{ route('parent.children') }}" class="text-sm font-semibold text-green-700">View all</a>
                        </div>
                        <div id="childrenOverview" class="mt-5 grid gap-4 md:grid-cols-2"></div>
                        <div id="childrenOverviewEmpty"
                            class="mt-5 hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                            <h3 class="mt-4 font-semibold">No children linked</h3>
                            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                                Your parent account does not have a connected student ID yet.
                                Ask the registrar to link a student account.
                            </p>
                        </div>
                    </section>
                    <section class="card p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="font-bold">Recent updates</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    Notifications sent to your account.
                                </p>
                            </div>
                            <button type="button" data-notifications class="text-sm font-semibold text-green-700">
                                Open
                            </button>
                        </div>
                        <div id="recentUpdates" class="mt-5 space-y-3"></div>
                        <p id="recentUpdatesEmpty" class="mt-5 hidden text-sm text-slate-500">
                            No recent notifications.
                        </p>
                    </section>
                </div>
                <section class="mt-6">
                    <div class="mb-4">
                        <h3 class="font-bold">Quick actions</h3>
                        <p class="mt-1 text-xs text-slate-400">
                            Jump directly to the information you need.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <a href="{{ route('parent.attendance') }}" class="parent-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <i data-lucide="calendar-check"></i>
                            </span>
                            <b class="mt-4 block">Attendance</b>
                            <span class="mt-1 block text-xs text-slate-400">Review attendance records</span>
                        </a>
                        <a href="{{ route('parent.grades') }}" class="parent-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="chart-no-axes-combined"></i>
                            </span>
                            <b class="mt-4 block">Grades</b>
                            <span class="mt-1 block text-xs text-slate-400">Check academic progress</span>
                        </a>
                        <a href="{{ route('parent.documents') }}" class="parent-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300">
                                <i data-lucide="file-text"></i>
                            </span>
                            <b class="mt-4 block">Documents</b>
                            <span class="mt-1 block text-xs text-slate-400">Track document requests</span>
                        </a> 
                        <a href="{{ route('parent.announcements') }}" class="parent-action-card">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
                                <i data-lucide="megaphone"></i>
                            </span>
                            <b class="mt-4 block">Announcements</b>
                            <span class="mt-1 block text-xs text-slate-400">Read college notices</span>
                        </a>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="childCardTemplate">
        <article class="card p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="relative h-11 w-11 shrink-0">
                        <img data-child-photo src="{{ asset('images/16432.png') }}" alt="Student profile photo"
                            class="h-11 w-11 rounded-2xl object-cover" />

                        <div data-child-initials
                            class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-green-100 text-sm font-bold text-green-700 dark:bg-green-950 dark:text-green-300">
                        </div>
                    </div>
                    <div>
                        <h3 data-child-name class="font-bold"></h3>
                        <p data-child-id class="text-xs text-slate-400"></p>
                    </div>
                </div>
                <span data-child-status></span>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-xs text-slate-400">Program / strand</p>
                    <p data-child-strand class="mt-1 truncate font-semibold"></p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-xs text-slate-400">Current average</p>
                    <p data-child-average class="mt-1 font-semibold"></p>
                </div>
            </div>
            <div
                class="mt-3 flex items-center justify-between rounded-xl bg-cyan-50 px-3 py-2.5 text-xs dark:bg-cyan-950/30">
                <span class="font-semibold text-cyan-800 dark:text-cyan-200">Competency progress</span><b
                    data-child-competency class="text-cyan-700 dark:text-cyan-300"></b>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <a data-grade-link href="{{ route('parent.grades') }}" class="rounded-xl bg-green-600 px-3 py-2 text-xs font-semibold text-white">
                    View grades
                </a>
                <a data-attendance-link href="{{ route('parent.attendance') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">
                    Attendance
                </a>
            </div>
        </article>
    </template><template id="updateTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex items-start justify-between gap-3">
                <b data-update-title class="text-sm"></b>
                <span data-update-date class="text-[11px] text-slate-400"></span>
            </div>
            <p data-update-message class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
        </div>
    </template>
</body>

</html>