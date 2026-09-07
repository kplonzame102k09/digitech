<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Parent Link Requests | Digitech College</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config = { darkMode: "class" };</script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="../css/custom.css" />
  @vite([ 'resources/css/app.css', 'resources/js/app.js' ])
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('components.sidebar')
    <div class="lg:pl-64">
        @include('components.header')
        <main class="p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl">
            <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Registrar workspace</p>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                <h2 class="text-3xl font-extrabold tracking-tight">Parent link requests</h2>
                <p class="mt-2 max-w-2xl text-white">Verify parent–student relationships before records are shared.
                </p>
                </div><span class="teacher-page-chip"><i data-lucide="shield-check" class="h-4 w-4"></i>Review
                required</span>
            </div>
            </section>
            <section class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-extrabold">Verification queue</h3>
                <p class="text-xs text-slate-400">Approve only relationships supported by registrar records.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                    <th class="p-4">Parent</th>
                    <th class="p-4">Student</th>
                    <th class="p-4">Submitted</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Actions</th>
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
    <script>
        lucide.createIcons();
    </script>

</body>

</html>