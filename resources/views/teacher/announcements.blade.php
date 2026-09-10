<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Class announcements | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-role="teacher" data-feature="announcements" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-3xl">
                <section class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-700">Communication center</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Announcements</h2>
                    <p class="mt-1 text-sm text-slate-500">Send clear updates to assigned learners and their parents.</p>
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
                            <input id="category" class="input rounded-xl border px-3 py-2.5 text-sm" placeholder="Category (e.g. Class)" />
                            <select id="audience" class="input rounded-xl border px-3 py-2.5 text-sm">
                                <option>All</option>
                                <option>Students</option>
                                <option>Parents</option>
                                <option>Teachers</option>
                            </select>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-800">
                                <i data-lucide="image-plus" class="h-4 w-4"></i>
                                Add photo
                                <input id="announcementImage" type="file" accept="image/*" class="hidden" />
                            </label>
                            <span id="announcementImageName" class="text-xs text-slate-400"></span>
                        </div>
                        <div id="announcementImagePreview" class="mt-3 hidden">
                            <div class="relative inline-block">
                                <img id="announcementImagePreviewImg" class="max-h-52 rounded-xl border border-slate-200 object-cover dark:border-slate-700" alt="Announcement photo preview" />
                                <button type="button" id="announcementImageRemove" aria-label="Remove photo"
                                    class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-white shadow">
                                    <i data-lucide="x" class="h-3 w-3"></i>
                                </button>
                            </div>
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