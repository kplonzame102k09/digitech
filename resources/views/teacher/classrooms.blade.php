<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Classrooms | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('teacher.components.sidebar')
    <div class="lg:pl-64">
        @include('teacher.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 overflow-hidden sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Learner management</p>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-extrabold tracking-tight">Your classrooms</h2>
                            <p class="mt-2 max-w-2xl text-slate-500">
                                Create and Manage Classrooms.
                            </p>
                        </div>
                        <!-- <button id="newClassroom" type="button"
                            class="inline-flex items-center gap-2 rounded bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            New classroom
                        </button> -->
                    </div>
                </section>
               <div class="mb-5 flex justify-end">
                    <button id="newClassroom" type="button"
                        class="inline-flex items-center gap-2 rounded bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        New classroom
                    </button>
                </div>
                <section class="mb-6 grid gap-4 sm:grid-cols-3">
                    
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Active classrooms</p>
                        <p id="classroomTotal" class="mt-2 text-3xl font-extrabold">0</p>
                        <p class="mt-1 text-xs text-slate-400">Open for joining</p>
                    </div>
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Students joined</p>
                        <p id="classroomStudents" class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">0</p>
                        <p class="mt-1 text-xs text-slate-400">Across active classrooms</p>
                    </div>
                    <div class="teacher-stat card p-5">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Archived</p>
                        <p id="classroomArchived" class="mt-2 text-3xl font-extrabold text-slate-500 dark:text-slate-400">0</p>
                        <p class="mt-1 text-xs text-slate-400">Closed for joining</p>
                    </div>
                </section>
                <div id="classroomError" role="alert"
                    class="mb-4 hidden rounded bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
                <section class="card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold">Classrooms</h3>
                            <p class="text-xs text-slate-400">Select a classroom to manage invites and roster.</p>
                        </div>
                    </div>
                    <div id="classroomGrid" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3"></div>
                    <div id="classroomEmpty" class="hidden py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        No classrooms yet. Create your first classroom to invite students.
                    </div>
                </section>
            </div>
        </main>
    </div>
    <dialog id="classroomDialog"
        class="w-[min(560px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <form id="classroomForm" method="dialog" class="card p-6">
            <h3 class="text-lg font-extrabold">New classroom</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Students join with the link or 9-digit code we generate.</p>
            <div class="mt-4 grid gap-4">
                <label class="text-sm font-semibold">Classroom name
                    <input id="classroomName" maxlength="50" required
                        class="input mt-1.5 w-full rounded border px-3 py-2.5" placeholder="e.g. Grade 11 - STEM A" />
                </label>
                <label class="text-sm font-semibold">Subject <span class="font-normal text-rose-500">* fixed — cannot be changed later</span>
                    <input id="classroomSubject" maxlength="120" required
                        class="input mt-1.5 w-full rounded border px-3 py-2.5" placeholder="e.g. Mathematics" />
                </label>
                <label class="text-sm font-semibold">Description <span class="font-normal text-slate-400">(optional)</span>
                    <textarea id="classroomDescription" rows="3" maxlength="2000"
                        class="input mt-1.5 w-full rounded border px-3 py-2.5" placeholder="Schedule, room, notes"></textarea>
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-classroom
                    class="rounded border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">Cancel</button>
                <button class="rounded bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-700">Create</button>
            </div>
        </form>
    </dialog>

    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'teacher/classrooms.js'])
</body>

</html>
