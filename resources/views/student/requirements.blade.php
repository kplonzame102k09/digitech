<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Requirements | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-student-page="requirements" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">Enrollment
                                checklist</p>
                            <h2 class="mt-2 text-3xl font-extrabold sm:text-4xl">Complete your requirements</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">
                                Track each required item and mark it submitted when ready for registrar review.
                            </p>
                        </div>
                        <span class="text-sm font-semibold text-white backdrop-blur">
                            <i data-lucide="upload-cloud" class="mr-2 inline h-4 w-4"></i>
                            Upload center
                        </span>
                    </div>
                </section>
                <section class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300"><i
                                data-lucide="list-checks"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Total requirements</p>
                        <p id="reqTotal" class="mt-1 text-3xl font-extrabold">0</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300"><i
                                data-lucide="clock-3"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Pending upload</p>
                        <p id="reqPending" class="mt-1 text-3xl font-extrabold text-amber-600">0</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300"><i
                                data-lucide="check-check"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Submitted</p>
                        <p id="reqSubmitted" class="mt-1 text-3xl font-extrabold text-emerald-600">0</p>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3"><span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300"><i
                                    data-lucide="folder-check"></i></span>
                            <div>
                                <h3 class="font-extrabold">Required documents</h3>
                                <p class="text-xs text-slate-400">Keep your enrollment file complete and ready for
                                    review.</p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4 text-left">Requirement</th>
                                    <th class="p-4 text-left">Status</th>
                                    <th class="p-4 text-left">Date submitted</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'student/student.js'])
</body>

</html>