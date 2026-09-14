<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Classroom | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100" data-classroom-id="{{ $classroomId }}">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl">
                <a href="{{ route('student.classrooms') }}"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to classrooms
                </a>
                <section class="mt-3 mb-7 overflow-hidden rounded-3xl p-6 shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Classroom</p>
                    <h2 id="detailName" class="mt-1 text-3xl font-extrabold tracking-tight">Loading…</h2>
                    <p id="detailMeta" class="mt-1 text-sm text-slate-500"></p>
                    <p id="detailDesc" class="mt-2 max-w-2xl text-sm text-white"></p>
                </section>

                <div id="detailError" role="alert"
                    class="mb-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>

                @include('partials.classroom-video', ['videoRole' => 'student'])

                <div class="mb-6 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900 sm:max-w-xl">
                    <button type="button" data-detail-tab="activities" aria-selected="true"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm font-semibold">Activities</button>
                    <button type="button" data-detail-tab="scores" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400">My scores</button>
                    <button type="button" data-detail-tab="classmates" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded px-3 py-2.5 text-sm text-slate-500 dark:text-slate-400">Classmates</button>
                </div>

                <section data-detail-panel="activities" class="space-y-4">
                    <div id="activityGroups" class="space-y-6"></div>
                </section>

                <section data-detail-panel="scores" class="hidden card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Activity</th>
                                    <th class="p-4">Term</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Score</th>
                                    <th class="p-4">Submitted</th>
                                </tr>
                            </thead>
                            <tbody id="scoreRows"></tbody>
                        </table>
                    </div>
                </section>

                <section data-detail-panel="classmates" class="hidden">
                    <div id="classmateGrid" class="grid gap-4 md:grid-cols-2"></div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <script src="{{ asset('js/classroom-video.js') }}"></script>
    @include('partials.portal-scripts', ['portalPage' => 'student/classroom-detail.js'])
</body>

</html>
