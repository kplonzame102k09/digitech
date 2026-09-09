<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Grades | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-parent-page="grades" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7">
                    <p class="text-sm font-semibold text-purple-600">
                        Academic progress
                    </p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Grades</h2>
                    <p class="mt-2 text-white">
                        Review published subject grades for each connected student.
                    </p>
                </section>
                <div id="gradesFilters" class="mb-6 flex flex-wrap items-center gap-2"></div>
                <div id="gradesSections" class="space-y-5"></div>
                <div id="gradesEmpty" class="hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No grades available</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        Published grades for your connected children will appear here.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="studentFilterTemplate">
        <a data-filter-link href="{{ ('#') }}" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 
        dark:border-slate-700 dark:text-slate-300">
            <span data-filter-label></span>
        </a>
    </template>
    <template id="gradeGroupTemplate">
        <section class="card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <div>
                    <h3 data-grade-student class="font-bold"></h3>
                    <p data-grade-student-id class="mt-1 text-xs text-slate-400"></p>
                </div>
                <div class="rounded-xl bg-purple-50 px-4 py-2 text-right dark:bg-purple-950">
                    <p class="text-[11px] text-purple-600 dark:text-purple-300">
                        Published average
                    </p>
                    <p data-published-average class="font-bold text-purple-700 dark:text-purple-200"></p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="p-4">Subject</th>
                            <th class="p-4">Term / period</th>
                            <th class="p-4">Grade</th>
                            <th class="p-4">Remarks</th>
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
            <td data-grade-value class="p-4 font-bold"></td>
            <td class="p-4"><span data-grade-remarks></span></td>
        </tr>
    </template>
</body>

</html>