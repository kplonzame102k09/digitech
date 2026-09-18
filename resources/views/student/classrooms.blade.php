<!doctype html>
<html>

<head>
    @include('partials.meta', ['pageTitle' => 'My Classrooms | Digitech College'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="student-portal min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('student.components.sidebar')
    <div class="lg:pl-64">
        @include('student.components.header', ['title' => 'My Classrooms', 'subtitle' => 'Classrooms'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl">
                <section class="mb-7 overflow-hidden p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Learning</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight">My classrooms</h2>
                    <p class="mt-2 max-w-2xl text-slate-500">Join your teacher's classroom with the 9-digit code or invite link.</p>
                </section>
                <section class="card mb-6 p-5 sm:p-6">
                    <h3 class="font-extrabold">Join a classroom</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter the code from your teacher (e.g. 482-913-027).</p>
                    <div id="joinError" role="alert"
                        class="mt-3 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
                    <form id="joinForm" class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <input id="joinCode" inputmode="numeric" autocomplete="off" maxlength="11"
                            class="input w-full rounded border px-4 py-3 font-mono text-lg tracking-widest sm:max-w-xs"
                            placeholder="000-000-000" />
                        <button class="rounded bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Join</button>
                    </form>
                </section>
                <section class="card p-5 sm:p-6">
                    <h3 class="font-extrabold">Enrolled classrooms</h3>
                    <div id="myClassrooms" class="mt-4 grid gap-4 md:grid-cols-2"></div>
                    <div id="myEmpty" class="hidden py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        You have not joined any classroom yet.
                    </div>
                </section>
                
                <section class="card p-5 sm:p-6 mt-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold">Recent Session History</h3>
                            <p class="text-xs text-slate-400">View recent live class sessions across your classrooms.</p>
                        </div>
                    </div>
                    <div id="sessionHistoryGrid" class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3"></div>
                    <div id="sessionHistoryEmpty" class="hidden py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        No session history yet.
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'student/classrooms.js'])
</body>

</html>
