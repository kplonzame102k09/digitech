<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Grades | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>
<body data-parent-page="grades" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero p-6 sm:p-8">
                    <p class="text-sm font-semibold text-blue-600">Academic progress</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Children grades</h2>
                    <p class="mt-2 text-white">Published grades shared by the college for your linked students.</p>
                </section>
                <div id="gradesFilters" class="mb-6 flex flex-wrap items-center gap-2"></div>
                <div id="gradesSections" class="space-y-4"></div>
                <div id="gradesEmpty" class="mt-6 hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="font-semibold">No published grades yet</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Grades appear here after teachers publish them.</p>
                </div>
            </div>
        </main>
    </div>
    <template id="studentFilterTemplate">
        <a data-filter-link href="#" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
            <span data-filter-label></span>
        </a>
    </template>
    <template id="gradeGroupTemplate">
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 data-grade-student class="font-bold"></h3>
                    <p data-grade-student-id class="text-xs text-slate-400"></p>
                </div>
                <p class="text-sm">Average: <b data-published-average></b></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="p-4 text-left">Subject</th>
                            <th class="p-4 text-left">Term</th>
                            <th class="p-4 text-left">Grade</th>
                            <th class="p-4 text-left">Remarks</th>
                        </tr>
                    </thead>
                    <tbody data-grade-rows></tbody>
                </table>
            </div>
        </section>
    </template>
    <template id="gradeRowTemplate">
        <tr class="border-t border-slate-200 dark:border-slate-800">
            <td data-grade-subject class="p-4 font-semibold"></td>
            <td data-grade-term class="p-4"></td>
            <td data-grade-value class="p-4"></td>
            <td class="p-4"><span data-grade-remarks></span></td>
        </tr>
    </template>
    @include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>
</html>
