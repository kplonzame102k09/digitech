<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Documents | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: "class" };</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>
<body data-parent-page="documents" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero p-6 sm:p-8">
                    <p class="text-sm font-semibold text-blue-600">Student services</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Children documents</h2>
                    <p class="mt-2 text-white">Track document requests and requirements for linked students.</p>
                </section>
                <section id="documentsTable" class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="font-bold">Document activity</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Item</th>
                                    <th class="p-4">Source</th>
                                    <th class="p-4">Date</th>
                                    <th class="p-4">Status</th>
                                </tr>
                            </thead>
                            <tbody id="documentRows"></tbody>
                        </table>
                    </div>
                </section>
                <div id="documentsEmpty" class="mt-6 hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="font-semibold">No document activity yet</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Requests and requirements will show here once available.</p>
                </div>
            </div>
        </main>
    </div>
    <template id="documentRowTemplate">
        <tr class="border-t border-slate-200 dark:border-slate-800">
            <td data-document-student class="p-4 font-semibold"></td>
            <td data-document-name class="p-4"></td>
            <td data-document-source class="p-4"></td>
            <td data-document-date class="p-4"></td>
            <td class="p-4"><span data-document-status></span></td>
        </tr>
    </template>
    @include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>
</html>
