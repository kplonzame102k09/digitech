<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Programs & TVET | Digitech College</title>
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
        @include('admin.components.header', ['title' => 'Programs & TVET', 'subtitle' => 'Academics'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-purple-600">Admissions catalogue</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Programs & TVET</h2>
                    <p class="mt-2 max-w-2xl text-white">
                        Manage the strand and track list the college offers, plus TVET
                        qualifications and training levels accepted on enrollments.
                    </p>
                </section>
                <div class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                    <button type="button" data-tab="programs"
                        class="programs-tab rounded-xl px-4 py-2.5 text-sm font-semibold">Strand / Track</button>
                    <button type="button" data-tab="tvet"
                        class="programs-tab rounded-xl px-4 py-2.5 text-sm font-semibold">TVET</button>
                </div>
                <section data-pane="programs" class="space-y-6">
                    <div class="card p-6">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-700">
                                <i data-lucide="layers" class="h-5 w-5"></i>
                            </span>
                            <div>
                                <h3 class="font-bold">Strands & Tracks</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Enter a strand or track with a short description below, then hit
                                    Save. One shared list — every entry here is accepted for both the
                                    strand and track fields on enrollment forms, and a value not on
                                    this list is rejected.
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <p class="text-xs text-slate-500">Entries</p>
                                <b id="programCount" class="mt-1 block text-2xl">0</b>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                                <p class="text-xs text-slate-500">Fallback to built-in defaults</p>
                                <b id="fallbackActive" class="mt-1 block text-2xl">Yes</b>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,150px)_auto]">
                            <label class="text-sm font-semibold">Name
                                <input id="programEntryInput" placeholder="e.g. ICT"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <label class="text-sm font-semibold">Description
                                <input id="programDescInput" placeholder="Short description"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <label class="text-sm font-semibold">Category
                                <select id="programCatInput"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">
                                    <option value="academics">Academics</option>
                                    <option value="techpro">TechPro</option>
                                </select>
                            </label>
                            <div class="flex items-end">
                                <button type="button" data-save-catalogue
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700">
                                    <i data-lucide="check" class="h-4 w-4"></i>
                                    Save
                                </button>
                            </div>
                        </div>
                        <p id="programFeedback" class="mt-3 text-xs text-slate-500"></p>
                        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="p-3">Name</th>
                                        <th class="p-3">Category</th>
                                        <th class="p-3">Description</th>
                                        <th class="w-12 p-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="programBody" class="divide-y divide-slate-200 dark:divide-slate-700"></tbody>
                            </table>
                        </div>
                        <p id="programHint" class="mt-3 text-xs text-slate-500"></p>
                    </div>
                </section>
                <section data-pane="tvet" class="hidden space-y-6">
                    <div class="card p-6">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                <i data-lucide="briefcase" class="h-5 w-5"></i>
                            </span>
                            <div>
                                <h3 class="font-bold">TVET qualifications</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Enter a qualification with its training levels (comma separated)
                                    and a short description below, then hit Save.
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <label class="text-sm font-semibold">Qualification
                                <input id="qualificationInput" placeholder="e.g. Computer Systems Servicing NC II"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <label class="text-sm font-semibold">Training Levels
                                <input id="levelEntryInput" placeholder="e.g. NC I, NC II"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <label class="text-sm font-semibold">Description
                                <input id="descriptionEntryInput" placeholder="Short description"
                                    class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" />
                            </label>
                            <div class="flex items-end">
                                <button type="button" data-save-catalogue
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700">
                                    <i data-lucide="check" class="h-4 w-4"></i>
                                    Save
                                </button>
                            </div>
                        </div>
                        <p id="tvetFeedback" class="mt-3 text-xs text-slate-500"></p>
                        <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="p-3">Qualification</th>
                                        <th class="p-3">Training Levels</th>
                                        <th class="p-3">Description</th>
                                        <th class="w-12 p-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="qualificationBody" class="divide-y divide-slate-200 dark:divide-slate-700"></tbody>
                            </table>
                        </div>
                        <p id="qualificationHint" class="mt-3 text-xs text-slate-500"></p>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/programs.js'])
</body>

</html>