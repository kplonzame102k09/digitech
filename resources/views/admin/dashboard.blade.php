<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>System Dashboard | Digitech College</title>
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
                        <p class="text-sm font-semibold text-purple-600">Administration</p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            System Overview
                        </h2>
                        <p class="mt-2 max-w-2xl text-white dark:text-slate-100">
                            Monitor operations, resolve pending work, and communicate with the
                            college community.
                        </p>
                    </section>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="card p-5">
                            <i data-lucide="graduation-cap" class="text-green-600"></i>
                            <p class="mt-4 text-sm text-slate-500">Total Students</p>
                            <b id="students" class="text-2xl"></b>
                        </div>
                        <div class="card p-5">
                            <i data-lucide="presentation" class="text-blue-600"></i>
                            <p class="mt-4 text-sm text-slate-500">Total Teachers</p>
                            <b id="teachers" class="text-2xl"></b>
                        </div>
                        <div class="card p-5">
                            <i data-lucide="clipboard-list" class="text-amber-600"></i>
                            <p class="mt-4 text-sm text-slate-500">Pending Enrollments</p>
                            <b id="enrollments" class="text-2xl"></b>
                            <a href="{{ route('admin.enrollment') }}" class="mt-2 inline-flex text-xs font-semibold text-green-700">Review queue →</a>
                        </div>
                        <div class="card p-5">
                            <i data-lucide="file-text" class="text-purple-600"></i>
                            <p class="mt-4 text-sm text-slate-500">Pending Documents</p>
                            <b id="docs" class="text-2xl"></b>
                            <a href="{{ route('admin.documents') }}" class="mt-2 inline-flex text-xs font-semibold text-green-700">Review queue →</a>
                        </div>
                    </div>
                    <section class="mt-6">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h3 class="font-bold">Quick actions</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    Move directly to the tasks administrators handle most.
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <a href="{{ route('admin.users') }}" class="card flex items-center gap-3 p-4">
                                <i data-lucide="user-plus" class="h-5 w-5 text-blue-600"></i>
                                <span>
                                    <b class="block text-sm">Manage users</b>
                                    <small class="text-xs text-slate-400">Create or review accounts</small>
                                </span>
                            </a>
                            <a href="{{ route('admin.enrollment') }}" class="card flex items-center gap-3 p-4">
                                <i data-lucide="clipboard-check" class="h-5 w-5 text-amber-600"></i>
                                <span>
                                    <b class="block text-sm">Review enrollment</b>
                                    <small class="text-xs text-slate-400">Process pending records</small>
                                </span>
                            </a>
                            <a href="{{ route('admin.documents') }}" class="card flex items-center gap-3 p-4">
                                <i data-lucide="file-check-2" class="h-5 w-5 text-purple-600"></i>
                                <span>
                                    <b class="block text-sm"> Process documents</b>
                                    <small class="text-xs text-slate-400">Update request status</small>
                                </span>
                            </a>
                            <a href="{{ route('admin.grades') }}" class="card flex items-center gap-3 p-4">
                                <i data-lucide="chart-no-axes-combined" class="h-5 w-5 text-green-600"></i>
                                <span>
                                    <b class="block text-sm">Review grades</b>
                                    <small class="text-xs text-slate-400">Monitor academic records</small>
                                </span>
                            </a>
                            <button type="button" data-focus-broadcast class="card flex items-center gap-3 p-4 text-left">
                                <i data-lucide="send" class="h-5 w-5 text-rose-600"></i>
                                <span>
                                    <b class="block text-sm">Broadcast notice</b>
                                    <small class="text-xs text-slate-400">Message the community</small>
                                </span>
                            </button>
                        </div>
                    </section>
                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="card overflow-hidden">
                            <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800">
                                <div>
                                    <h3 class="font-bold">Enrollment queue</h3>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Records waiting for administrator action.
                                    </p>
                                </div>
                                <a href="{{ route('admin.enrollment') }}" class="text-sm font-semibold text-green-700">View all</a>
                            </div>
                            <div id="enrollmentQueue" class="divide-y divide-slate-100 dark:divide-slate-800"></div>
                            <p id="enrollmentQueueEmpty" class="hidden p-6 text-sm text-slate-500">
                                No pending enrollment records.
                            </p>
                        </section>
                        <section class="card overflow-hidden">
                            <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800">
                                <div>
                                    <h3 class="font-bold">Document queue</h3>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Requests that need review or processing.
                                    </p>
                                </div>
                                <a href="{{ route('admin.documents') }}" class="text-sm font-semibold text-green-700">View all</a>
                            </div>
                            <div id="documentQueue" class="divide-y divide-slate-100 dark:divide-slate-800"></div>
                            <p id="documentQueueEmpty" class="hidden p-6 text-sm text-slate-500">
                                No pending document requests.
                            </p>
                        </section>
                    </div>
                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="card p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-bold">Attendance overview</h3>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Current attendance records across connected students.
                                    </p>
                                </div>
                                <span id="attendanceRate" class="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700 dark:bg-green-950 
                                    dark:text-green-300">
                                    0%
                                </span>
                            </div>
                            <div class="mt-5 grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-green-50 p-3 dark:bg-green-950/50">
                                    <p class="text-xs text-green-700 dark:text-green-300"> Present </p>
                                    <b id="attendancePresent" class="mt-1 block text-xl"></b>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/50">
                                    <p class="text-xs text-amber-700 dark:text-amber-300">Late</p>
                                    <b id="attendanceLate" class="mt-1 block text-xl"></b>
                                </div>
                                <div class="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/50">
                                    <p class="text-xs text-rose-700 dark:text-rose-300">Absent</p>
                                    <b id="attendanceAbsent" class="mt-1 block text-xl"></b>
                                </div>
                            </div>
                            <div id="attendanceBars" class="mt-6 flex h-36 items-end gap-2 border-b border-slate-200 pb-1 dark:border-slate-800"></div>
                            <p id="attendanceEmpty" class="hidden mt-5 text-sm text-slate-500">
                                No attendance records have been entered yet.
                            </p>
                        </section>
                        <section class="card p-6">
                            <div>
                                <h3 class="font-bold">System statistics</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    A live summary of records stored in the portal.
                                </p>
                            </div>
                            <div id="stats" class="mt-5 space-y-3"></div>
                            <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
                                <h4 class="text-sm font-bold">Attendance trend</h4>
                                <svg id="attendanceChart" class="mt-4 h-32 w-full overflow-visible" viewBox="0 0 500 130"
                                    role="img" aria-label="Attendance trend chart"></svg>
                            </div>
                        </section>
                    </div>
                    <section id="broadcastSection" class="card mt-6 p-6">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950">
                                <i data-lucide="send" class="h-5 w-5"></i>
                            </span>
                            <div>
                                <h3 class="font-bold">Broadcast announcement</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Publish a notice and create notifications for the selected
                                    audience.
                                </p>
                            </div>
                        </div>
                        <form id="announcementForm" class="mt-6 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-semibold">
                                Title
                                <input id="announcementTitle" required maxlength="100" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5"
                                    placeholder="e.g. Campus maintenance notice" />
                            </label>
                            <label class="text-sm font-semibold">
                                Category
                                <select id="announcementCategory" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                                    <option>General</option>
                                    <option>Academic</option>
                                    <option>Enrollment</option>
                                    <option>Registrar</option>
                                    <option>Events</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold">
                                Audience
                                <select id="announcementAudience" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                                    <option value="all">Everyone</option>
                                    <option value="student">Students</option>
                                    <option value="parent">Parents</option>
                                    <option value="teacher">Teachers</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold md:col-span-2">
                                Message
                                <textarea id="announcementMessage" required maxlength="500" rows="4" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" placeholder="Write a clear, concise announcement..."></textarea>
                            </label>
                            <div class="flex flex-wrap items-center gap-3 md:col-span-2">
                                <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">
                                    Publish announcement
                                </button>
                                <span id="announcementFeedback" class="text-xs text-slate-500"></span>
                            </div>
                        </form>
                    </section>
                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="card p-6">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-bold">Recent enrollment activity</h3>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Latest status changes across enrollment records.
                                    </p>
                                </div>
                                <a href="{{ route('admin.enrollment') }}" class="text-sm font-semibold text-green-700">Open records</a>
                            </div>
                            <div id="recent" class="mt-5 space-y-3"></div>
                            <p id="recentEmpty" class="hidden mt-5 text-sm text-slate-500">
                                No enrollment activity yet.
                            </p>
                        </section>
                        <section class="card p-6">
                            <div>
                                <h3 class="font-bold">Recent broadcasts</h3>
                                <p class="mt-1 text-xs text-slate-400">
                                    Announcements most recently published by administrators.
                                </p>
                            </div>
                            <div id="recentAnnouncements" class="mt-5 space-y-3"></div>
                            <p id="announcementsEmpty" class="hidden mt-5 text-sm text-slate-500">
                                No announcements published yet.
                            </p>
                        </section>
                    </div>
                </div>
            </main>
        </div>
        <div id="modalRoot"></div>
        <template id="queueItemTemplate">
            <div class="flex items-center justify-between gap-4 p-4">
                <div class="min-w-0">
                    <b data-queue-title class="block truncate text-sm"></b>
                    <span data-queue-meta class="mt-1 block text-xs text-slate-400"></span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span data-queue-status></span>
                    <a data-queue-link class="text-xs font-semibold text-green-700">Review</a>
                </div>
            </div>
        </template>
        <template id="statRowTemplate">
            <div class="flex items-center justify-between text-sm">
                <span data-stat-label class="text-slate-500"></span>
                <b data-stat-value></b>
            </div>
        </template>
        <template id="recentRowTemplate">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <span data-recent-student class="text-sm font-semibold"></span>
                <span data-recent-status></span>
            </div>
        </template>
        <template id="announcementRowTemplate">
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <b data-announcement-title class="text-sm"></b>
                    <time data-announcement-date class="text-[11px] text-slate-400"></time>
                </div>
                <p data-announcement-meta class="mt-1 text-xs text-slate-500 dark:text-slate-400"></p>
            </div>
        </template>

    @include('partials.portal-scripts', ['portalPage' => 'admin/dashboard.js'])
</body>
</html>