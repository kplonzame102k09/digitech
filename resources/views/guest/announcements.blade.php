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
</head>

<body data-role="guest" data-feature="announcements" class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('guest.components.sidebar')
    <div class="lg:pl-64">
        @include('guest.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-3xl">
                <section class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">College
                        communication</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Announcements</h2>
                    <p class="mt-1 text-sm text-slate-500">Stay up to date with college and public notices.
                    </p>
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