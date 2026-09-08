<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Announcements | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-role="admin" data-feature="announcements" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Communication center</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Announcements</h2>
                            <p class="mt-2 max-w-2xl text-white">Create and distribute college-wide notices.</p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Portal workspace
                        </span>
                    </div>
                </section>
                <section class="teacher-toolbar card mb-6 p-5 sm:p-6">
                    <div class="mb-5">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">Communication center</p>
                        <h3 class="mt-1 text-xl font-extrabold">Publish announcement</h3>
                        <p class="mt-1 text-sm text-slate-500">Keep your college community informed with clear, timely updates.</p>
                    </div>
                    <form id="announcementForm" class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-semibold">
                            Title
                            <input id="title" class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="Announcement title" required>
                        </label>
                        <label class="text-sm font-semibold">
                            Category
                            <input id="category" class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="e.g. Registrar">
                        </label>
                        <label class="text-sm font-semibold">
                            Audience
                            <select id="audience" class="input mt-1.5 w-full rounded-xl border px-3 py-3">
                                <option>All</option>
                                <option>Students</option>
                                <option>Parents</option>
                                <option>Teachers</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold md:col-span-2">
                            Message
                            <textarea id="message" class="input mt-1.5 w-full rounded-xl border px-3 py-3" rows="4" placeholder="Write your announcement" required></textarea>
                        </label>
                        <div class="flex justify-end md:col-span-2">
                            <button class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white">Publish announcement</button>
                        </div>
                    </form>
                </section>
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="font-extrabold">Announcement feed</h3>
                        <p class="text-xs text-slate-400">The latest notices available to this role.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Title</th>
                                    <th class="p-4">Audience</th>
                                    <th class="p-4">Category</th>
                                    <th class="p-4">Date</th>
                                    <th class="p-4">Message</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <template id="auditRowTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex justify-between gap-2">
                <b data-audit-title class="text-sm"></b>
                <time data-audit-date class="text-[11px] text-slate-400"></time>
            </div>
            <p data-audit-meta class="mt-1 text-xs text-slate-500"></p>
            <p data-audit-notes class="mt-1 text-xs"></p>
        </div>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'workflows.js'])
</body>
</html>