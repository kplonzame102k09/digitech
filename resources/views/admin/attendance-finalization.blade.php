<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Finalize attendance | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body data-role="admin" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header', ['title' => 'Finalize Attendance', 'subtitle' => 'Admin Portal'])
        <main class="p-4 sm:p-6 lg:p-8">
        <script>window.PORTAL_USER_ID = @json(auth()->user()->user_id); window.PORTAL_IS_ADMIN = @json((bool) auth()->user()->isAdmin());</script>
            <div class="mx-auto max-w-7xl">
                <section class="mb-2 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Academic operations</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Finalize attendance</h2>
                            <p class="mt-2 max-w-2xl text-slate-500">Advisers submit each reviewed day here. Review a package's official marks and classroom evidence, then finalize it to lock the record as official.</p>
                        </div>
                        <span class="teacher-page-chip">
                            <i data-lucide="shield-check" class="h-4 w-4"></i>
                            Portal workspace
                        </span>
                    </div>
                </section>
                <div id="appError" role="alert" class="mb-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
                <div id="app"></div>
                <div id="modalRoot"></div>
            </div>
        </main>
    </div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/attendance-finalization.js'])
</body>

</html>