<!doctype html>
<html>

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
    @vite([ 'resources/css/app.css', 'resource/js/app.js' ])
</head>

<body data-role="student" data-feature="announcements" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">College
                                communication</p>
                            <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Announcements</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-white">Stay up to date with college and
                                class notices.
                            </p>
                        </div><span class=" text-sm font-semibold text-white backdrop-blur"><i data-lucide="megaphone"
                                class="mr-2 inline h-4 w-4"></i>Student updates</span>
                    </div>
                </section>
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                        <h3 class="font-bold">Announcement feed</h3>
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
    <div id="modalRoot"></div>
</body>

</html>