<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Children | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body data-parent-page="children" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7">
                    <p class="text-sm font-semibold text-green-600">Family records</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">My Children</h2>
                    <p class="mt-2 text-slate-white">
                        View the student accounts connected to your parent profile.
                    </p>
                </section>
                <div id="childrenGrid" class="grid gap-5 md:grid-cols-2"></div>
                <div id="childrenEmpty"
                    class="hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No children linked</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        No student account is currently linked to this parent profile.
                        Contact the registrar to add or correct the Child / Student ID.
                    </p>
                </div>
                <section class="card mt-6 p-5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="info" class="mt-0.5 h-5 w-5 text-blue-600"></i>
                        <div>
                            <h3 class="font-semibold">Need to update a child link?</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                For privacy and record-integrity reasons, child links are
                                maintained by the registrar. Please provide your parent ID
                                (<span data-parent-id></span>) and the student ID that should
                                be connected.
                            </p>
                        </div>
                    </div>
                </section>
                <section class="card mt-6 p-5">
                    <h3 class="font-semibold">Request a child link</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Requests are sent to the registrar for verification before
                        records become visible.
                    </p>
                    <form id="linkRequestForm" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input id="requestedStudentId" required class="input w-full rounded-xl border px-3 py-2.5" placeholder="Student ID, e.g. STU-2026-XXXXXX">
                        <button class="rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white">
                            Send request
                        </button>
                    </form>
                    <div id="linkRequestStatus" class="mt-3 text-sm text-slate-500"></div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="childCardTemplate">
        <article class="card p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="relative h-11 w-11 shrink-0">
                        <img data-child-photo src="{{ asset('images/16432.png') }}" alt="Student profile photo" class="h-11 w-11 rounded-2xl object-cover" />
                        <div data-child-initials
                            class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-green-100 text-sm font-bold text-green-700 dark:bg-green-950 dark:text-green-300">
                        </div>
                    </div>
                </div>
                <div>
                    <h3 data-child-name class="font-bold"></h3>
                    <p data-child-id class="text-xs text-slate-400"></p>
                </div>
            </div>
            <span data-child-status></span>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-xs text-slate-400">Program / strand</p>
                    <p data-child-strand class="mt-1 truncate font-semibold"></p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-xs text-slate-400">Current average</p>
                    <p data-child-average class="mt-1 font-semibold"></p>
                </div>
            </div>
            <div
                class="mt-3 flex items-center justify-between rounded-xl bg-cyan-50 px-3 py-2.5 text-xs dark:bg-cyan-950/30">
                <span class="font-semibold text-cyan-800 dark:text-cyan-200">Competency progress</span>
                <b data-child-competency class="text-cyan-700 dark:text-cyan-300"></b>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <a data-grade-link href="{{ route('parent.grades') }}" class="rounded-xl bg-green-600 border border-slate-200  px-6 py-4 text-xs font-semibold text-white">
                    View grades
                </a>
                <a data-attendance-link href="{{ route('parent.attendance') }}" class="rounded-xl bg-blue-500 border border-slate-200 px-6 py-4 text-xs font-semibold text-white-700">
                    Attendance
                </a>
            </div>
        </article>
    </template>
</body>
</html>