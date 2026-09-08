<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Teacher Dashboard | Digitech College</title>
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
                    <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">
                                <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                                Faculty workspace
                            </div>
                            <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                                Welcome back, 
                                <span id="name"></span>.
                            </h2>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-white-500">
                                Stay on top of your assigned learners, review records that need attention, and keep assessments moving forward.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('teacher.students') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur hover:bg-white/25">
                                <i data-lucide="users-round" class="h-4 w-4"></i>
                                View roster
                            </a>
                            <a href="{{ route('teacher.competencies') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                <i data-lucide="clipboard-pen-line" class="h-4 w-4"></i>
                                Assessments
                            </a>
                        </div>
                    </div>
                    <div class="teacher-hero-glow"></div>
                </section>
                <section id="dashboardStats" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></section>
                <section class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_1fr]">
                    <div class="card p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600">Your roster
                                </p>
                                <h3 class="mt-1 text-xl font-extrabold">Assigned students</h3>
                                <p class="mt-1 text-sm text-white">Learners connected to your teaching assignments.
                                </p>
                            </div>
                            <a href="{{ route('teacher.students') }}" class="inline-flex items-center gap-1 text-sm font-bold text-emerald-700">
                                View all
                                <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                            </a>
                        </div>
                        <div id="list" class="mt-5 grid gap-3 md:grid-cols-2"></div>
                    </div>
                    <div class="card p-5 sm:p-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">Action center</p>
                            <h3 class="mt-1 text-xl font-extrabold">Review queue</h3>
                            <p class="mt-1 text-sm text-slate-500">Items that may need your attention today.</p>
                        </div>
                        <div id="dashboardQueue" class="mt-5 space-y-2"></div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'teacher/teacher.js'])
</body>

</html>