<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Documents | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-parent-page="documents" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7">
                    <p class="text-sm font-semibold text-amber-600">
                        Registrar services
                    </p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Documents</h2>
                    <p class="mt-2 text-white">
                        Track document requests and outstanding requirements for your
                        children.
                    </p>
                </section>
                <section id="documentsTable" class="card hidden overflow-hidden">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                        <h3 class="font-bold">Document activity</h3>
                        <p class="mt-1 text-xs text-slate-400">
                            Status updates are maintained by the registrar and admissions
                            team.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <tr>
                                    <th class="p-4">Item</th>
                                    <th class="p-4">Student</th>
                                    <th class="p-4">Type</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4">Date</th>
                                </tr>
                            </thead>
                            <tbody id="documentRows"></tbody>
                        </table>
                    </div>
                </section>
                <div id="documentsEmpty"
                    class="hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No document activity</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        Document requests and requirements for your children will appear
                        here.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="documentRowTemplate">
        <tr class="border-t border-slate-200 dark:border-slate-800">
            <td data-document-name class="p-4 font-semibold"></td>
            <td data-document-student class="p-4"></td>
            <td data-document-source class="p-4 text-slate-500 dark:text-slate-400"></td>
            <td class="p-4"><span data-document-status></span></td>
            <td data-document-date class="whitespace-nowrap p-4 text-slate-500 dark:text-slate-400"></td>
        </tr>
    </template>
    @include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>

</html>