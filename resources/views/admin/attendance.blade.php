<!doctype html>
<html lang="en">

<head>
    @include('partials.meta', ['pageTitle' => 'Attendance administration | Digitech College'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-role="admin" data-feature="attendance" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header', ['title' => 'Attendance', 'subtitle' => 'Admin Portal'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-2 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Academic operations</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Attendance administration</h2>
                            <p class="mt-2 max-w-2xl text-slate-500">Review attendance analytics and finalized attendance records.</p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Portal workspace
                        </span>
                    </div>
                </section>
                <section class="mt-5 card mb-6 p-5 sm:p-6">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400">
                                Teacher analytics
                            </p>
                            <h3 class="mt-1 text-xl font-extrabold">Attendance by teacher</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">View attendance recording statistics per teacher and admin recorder. Click a card to see each student's finalized attendance.</p>
                        </div>
                    </div>
                    <div id="teacherAnalytics" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Teacher analytics cards will be rendered here -->
                    </div>
                </section>
                <section class="card mb-6 p-5 sm:p-6">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">
                                Overall analytics
                            </p>
                            <h3 class="mt-1 text-xl font-extrabold">Attendance trends</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">View overall attendance patterns over time. Rate counts Present, Late, and Excused as attended.</p>
                        </div>
                    </div>
                    <div class="grid gap-6 md:grid-cols-4 mb-6">
                        <div class="rounded border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-[10px] uppercase tracking-wider text-slate-400">Total Records</p>
                            <p id="totalRecords" class="mt-2 text-2xl font-bold text-slate-700 dark:text-slate-200">0</p>
                        </div>
                        <div class="rounded border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-[10px] uppercase tracking-wider text-slate-400">Overall Rate</p>
                            <p id="overallRate" class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">0%</p>
                        </div>
                        <div class="rounded border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-[10px] uppercase tracking-wider text-slate-400">Present (incl. Late / Excused)</p>
                            <p id="totalPresent" class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">0</p>
                        </div>
                        <div class="rounded border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-[10px] uppercase tracking-wider text-slate-400">Absent</p>
                            <p id="totalAbsent" class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">0</p>
                        </div>
                    </div>
                    <div class="rounded border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                        <canvas id="attendanceChart" height="100"></canvas>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'workflows.js'])
</body>
</html>