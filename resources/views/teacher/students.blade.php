<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Assigned Students | Digitech College</title>
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
       @include('teacher.components.header', ['title' => 'Assigned Students', 'subtitle' => 'Learner management'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 overflow-hidden sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Learner management</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Your student roster</h2>
                            <p class="mt-2 max-w-2xl text-slate-500">
                                Quickly find a learner, check their record health,
                                and open a complete student snapshot.
                            </p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Assigned access only
                        </span>
                    </div>
                </section>
                <section class="mt-6 mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total assigned</p>
                        <p id="studentTotal" class="mt-2 text-3xl font-extrabold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Learners in your roster</p>
                    </div>
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">With enrollment</p>
                        <p id="studentEnrolled" class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">0</p>
                        <p class="mt-1 text-xs text-slate-400">Enrollment record found</p>
                    </div>
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Needs attention</p>
                        <p id="studentAttention" class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">0</p>
                        <p class="mt-1 text-xs text-slate-400">No enrollment record</p>
                    </div>
                </section>
                <div id="rowsShell" class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">User ID</th>
                                    <th class="p-4">Strand / track</th>
                                    <th class="p-4">Enrollment</th>
                                    <th class="p-4">Record coverage</th>
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
@include('partials.portal-scripts', ['portalPage' => 'teacher/teacher.js'])
</body>

</html>