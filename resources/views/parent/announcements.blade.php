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

<body data-parent-page="announcements" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
        @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-green-600">College news</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">Announcements</h2>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">
                        Important updates and notices relevant to your family.
                    </p>
                </section>
                <div id="announcementList" class="space-y-4"></div>
                <div id="announcementsEmpty" class="hidden rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
                    <h3 class="mt-4 font-semibold">No announcements yet</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-white">
                        New college announcements will appear here when they are
                        published.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    <template id="announcementTemplate">
        <article class="card p-5">
            <div class="flex items-start gap-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600 dark:bg-green-950">
                    <i data-lucide="megaphone" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <h3 data-announcement-title class="font-bold"></h3>
                        <time data-announcement-date class="text-xs text-slate-400"></time>
                    </div>
                    <p data-announcement-message class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"></p>
                    <span data-announcement-category
                        class="mt-3 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    </span>
                </div>
            </div>
        </article>
    </template>
@include('partials.portal-scripts', ['portalPage' => 'parent/parent.js'])
</body>

</html>