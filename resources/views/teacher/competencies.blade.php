<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Competency Management | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="teacher-hero mb-7 overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-600">TVET workspace</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Competency assessments</h2>
                            <p class="mt-2 max-w-2xl text-white">
                                Review the competencies assigned by administration and record
                                clear, evidence-based outcomes for your learners.
                            </p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="clipboard-check" class="h-4 w-4"></i>
                            Teacher assessment view
                        </span>
                    </div>
                </section>
                <section id="competencySummary" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"></section>
                <div class="mb-5 flex items-start gap-3 rounded-2xl border border-violet-100 bg-violet-50/70 p-4 text-sm text-violet-900 dark:border-violet-900/50 dark:bg-violet-950/30 dark:text-violet-200">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-violet-600 shadow-sm dark:bg-violet-950">
                        <i data-lucide="lightbulb" class="h-4 w-4"></i>
                    </span>
                    <p>
                        <b>Assessment tip:</b> 
                        update the outcome status, date, assessor, evidence link, and remarks so
                        students and administrators have a complete record.
                    </p>
                </div>
                <div id="cards" class="grid gap-4 lg:grid-cols-2"></div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
</body>

</html>