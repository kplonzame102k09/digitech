<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Document Requests | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-student-page="documents" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7">
                    <div
                        class="student-hero mb-7 p-6 sm:p-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Student services</p>
                            <h2 class="mt-2 text-3xl font-extrabold tracking-tight">Request official documents</h2>
                            <p class="mt-2 max-w-2xl text-white">Submit a request, share its purpose, and track each
                                document from
                                processing to release.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('formBox').classList.toggle('hidden')"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold 
                            text-white hover:bg-emerald-700">
                            <i data-lucide="plus"></i>
                            New request
                        </button>
                    </div>
                </section>
                <section class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="portal-stat card p-5">
                        <span class="portal-stat-icon bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                            <i data-lucide="files"></i>
                        </span>
                        <p class="mt-4 text-sm text-slate-500">Total requests</p>
                        <p id="docTotal" class="mt-1 text-3xl font-extrabold">0</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300"><i
                                data-lucide="clock-3"></i></span>
                        <p class="mt-4 text-sm text-slate-500">In progress</p>
                        <p id="docPending" class="mt-1 text-3xl font-extrabold text-amber-600">0</p>
                    </div>
                    <div class="portal-stat card p-5"><span
                            class="portal-stat-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300"><i
                                data-lucide="badge-check"></i></span>
                        <p class="mt-4 text-sm text-slate-500">Ready or released</p>
                        <p id="docReady" class="mt-1 text-3xl font-extrabold text-emerald-600">0</p>
                    </div>
                </section>
                <section id="formBox" class="student-toolbar card mb-6 hidden p-5 sm:p-6">
                    <div class="mb-5 flex items-start gap-3"><span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300"><i
                                data-lucide="file-plus-2"></i></span>
                        <div>
                            <h3 class="font-extrabold">Start a new request</h3>
                            <p class="mt-1 text-sm text-slate-500">Provide enough detail for the registrar to process it
                                quickly.</p>
                        </div>
                    </div>
                    <form id="docForm" class="grid gap-5 md:grid-cols-2"><label class="text-sm font-semibold">Document
                            type<select id="type" class="input mt-1.5 w-full rounded-xl border px-3 py-3">
                                <option>Form 137</option>
                                <option>Good Moral Certificate</option>
                                <option>Transcript of Records</option>
                                <option>Diploma</option>
                            </select></label><label class="text-sm font-semibold">Purpose<input id="purpose" required
                                maxlength="160" class="input mt-1.5 w-full rounded-xl border px-3 py-3"
                                placeholder="e.g. Scholarship application" /></label><label
                            class="text-sm font-semibold">Number of
                            copies<input id="copies" type="number" min="1" max="10" value="1"
                                class="input mt-1.5 w-full rounded-xl border px-3 py-3" /></label><label
                            class="text-sm font-semibold">Notes <span
                                class="font-normal text-slate-400">(optional)</span><input id="notes" maxlength="300"
                                class="input mt-1.5 w-full rounded-xl border px-3 py-3"
                                placeholder="Additional instructions" /></label>
                        <div class="flex justify-end md:col-span-2"><button
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700"><i
                                    data-lucide="send"></i>Submit request</button></div>
                    </form>
                </section>
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div class="flex items-center gap-3"><span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300"><i
                                    data-lucide="history"></i></span>
                            <div>
                                <h3 class="font-extrabold">Request history</h3>
                                <p class="text-xs text-slate-400">Status updates from the registrar appear here.</p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4 text-left">Document</th>
                                    <th class="p-4 text-left">Purpose</th>
                                    <th class="p-4 text-left">Date</th>
                                    <th class="p-4 text-left">Status</th>
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