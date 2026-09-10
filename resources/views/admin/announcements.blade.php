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
            <div class="mx-auto max-w-3xl">
                <section class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Communication center</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Announcements</h2>
                    <p class="mt-1 text-sm text-slate-500">A live feed of college-wide notices.</p>
                </section>
                <section class="card mb-6 overflow-hidden">
                    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                            <i data-lucide="megaphone" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <h3 class="font-extrabold">Share an update</h3>
                            <p class="text-xs text-slate-400">Publish a notice to the college community</p>
                        </div>
                    </div>
                    <form id="announcementForm" class="p-5">
                        <textarea id="message" rows="3" class="input w-full rounded-xl border px-3 py-3" placeholder="What's the announcement?" required></textarea>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <input id="title" class="input rounded-xl border px-3 py-2.5 text-sm" placeholder="Title *" />
                            <input id="category" class="input rounded-xl border px-3 py-2.5 text-sm" placeholder="Category (e.g. Registrar)" />
                            <select id="audience" class="input rounded-xl border px-3 py-2.5 text-sm">
                                <option>All</option>
                                <option>Students</option>
                                <option>Parents</option>
                                <option>Teachers</option>
                                <option>Guests</option>
                            </select>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-xs text-slate-400">Reaches every selected audience instantly.</span>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                <i data-lucide="send" class="h-4 w-4"></i>
                                Publish
                            </button>
                        </div>
                    </form>
                </section>
                <section>
                    <div class="mb-3 flex items-center gap-2 px-1">
                        <i data-lucide="newspaper" class="h-4 w-4 text-slate-400"></i>
                        <h3 class="text-sm font-bold text-slate-500">Latest updates</h3>
                    </div>
                    <div id="rows" class="space-y-4"></div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'workflows.js'])
</body>

</html>