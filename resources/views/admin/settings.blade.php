<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>System Settings | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-7 student-hero mb-7 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-purple-600">Administration</p>
                    <h2 class="mt-1 text-3xl font-bold tracking-tight">
                        Settings & Controls
                    </h2>
                    <p class="mt-2 max-w-2xl text-white">
                        Configure portal behavior, protect database data, and keep an
                        exportable backup.
                    </p>
                </section>
                <form id="settingsForm">
                    <div class="grid gap-5 lg:grid-cols-2">
                        <section class="card p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-700"><i
                                        data-lucide="palette" class="h-5 w-5"></i></span>
                                <div>
                                    <h3 class="font-bold">Appearance</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                            Theme preference is saved with your portal settings.
                                    </p>
                                </div>
                            </div>
                            <button type="button" data-theme-toggle
                                class="mt-5 rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">
                                Toggle dark mode
                            </button>
                        </section>
                        <section class="card p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i
                                        data-lucide="user-plus" class="h-5 w-5"></i></span>
                                <div>
                                    <h3 class="font-bold">Registration</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Control which roles can be registered from the sign-up
                                        flow.
                                    </p>
                                </div>
                            </div>
                            <label class="mt-5 flex items-center gap-3 text-sm"><input id="teacherReg" type="checkbox"
                                    class="h-4 w-4" />Enable teacher registration</label><label
                                class="mt-4 flex items-center gap-3 text-sm"><input id="adminReg" type="checkbox"
                                    class="h-4 w-4" />Enable
                                admin registration</label>
                        </section>
                        <section class="card p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-700"><i
                                        data-lucide="school" class="h-5 w-5"></i></span>
                                <div>
                                    <h3 class="font-bold">Institution and academic defaults</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        These values are used by enrollment and academic
                                        workflows.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <label class="text-sm font-semibold sm:col-span-2">Institution name<input
                                        id="institutionName"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                                    class="text-sm font-semibold">Current school year<input id="schoolYear"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                                    class="text-sm font-semibold">Passing grade<input id="passingGrade" type="number"
                                        min="0" max="100"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label
                                    class="text-sm font-semibold sm:col-span-2">Enrollment deadline<input
                                        id="enrollmentDeadline" type="date"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label>
                            </div>
                        </section>
                        <section class="card p-6">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><i
                                        data-lucide="bell-ring" class="h-5 w-5"></i></span>
                                <div>
                                    <h3 class="font-bold">Notification defaults</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Choose which roles receive broadcast announcements by
                                        default.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <label class="flex items-center gap-3 text-sm"><input id="notifyStudents"
                                        type="checkbox" class="h-4 w-4" />Students</label><label
                                    class="flex items-center gap-3 text-sm"><input id="notifyParents" type="checkbox"
                                        class="h-4 w-4" />Parents</label><label
                                    class="flex items-center gap-3 text-sm"><input id="notifyTeachers" type="checkbox"
                                        class="h-4 w-4" />Teachers</label><label
                                    class="flex items-center gap-3 text-sm"><input id="notifyAdmins" type="checkbox"
                                        class="h-4 w-4" />Admins</label>
                            </div>
                        </section>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-4">
                        <p id="settingsFeedback" class="text-sm text-slate-500"></p>
                        <button type="submit" class="rounded-xl bg-green-600 px-5 py-2.5 font-semibold text-white">
                            Save settings
                        </button>
                    </div>
                </form>
                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <section class="card p-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i
                                    data-lucide="hard-drive-download" class="h-5 w-5"></i></span>
                            <div>
                                <h3 class="font-bold">Backup and restore</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Export the MySQL portal collections or restore a previously
                                    downloaded JSON backup.
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button type="button" data-export-backup
                                class="rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                                Download backup</button><label
                                class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2.5 font-semibold dark:border-slate-700">Restore
                                backup<input id="backupFile" type="file" accept="application/json,.json"
                                    class="hidden" /></label>
                        </div>
                        <p id="backupFeedback" class="mt-3 text-xs text-slate-500"></p>
                    </section>
                    <section class="card p-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-700"><i
                                    data-lucide="activity" class="h-5 w-5"></i></span>
                            <div>
                                <h3 class="font-bold">System health</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Current MySQL collection counts and backup status.
                                </p>
                            </div>
                        </div>
                        <div id="healthGrid" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
                        <p id="lastBackup" class="mt-4 text-xs text-slate-500"></p>
                    </section>
                </div>
                <section class="card mt-5 p-6">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800"><i
                                data-lucide="history" class="h-5 w-5"></i></span>
                        <div>
                            <h3 class="font-bold">Recent system audit</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Administrative changes and data-management events.
                            </p>
                        </div>
                    </div>
                    <div id="auditRows" class="mt-5 space-y-3"></div>
                    <p id="auditEmpty" class="hidden mt-5 text-sm text-slate-500">
                        No system audit events recorded.
                    </p>
                </section>
                <section class="card mt-5 p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><i
                                data-lucide="trash-2" class="h-5 w-5"></i></span>
                        <div>
                            <h3 class="font-bold text-rose-700">Database data controls</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Tick the collections you want to clear, then reset them all with one button.
                                These actions cannot be undone without a backup.
                            </p>
                        </div>
                    </div>
                    <div id="resetOptions" class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="users" class="h-4 w-4 accent-rose-600" />Users
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="enrollments" class="h-4 w-4 accent-rose-600" />Enrollments
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="documentRequests" class="h-4 w-4 accent-rose-600" />Documents
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="grades" class="h-4 w-4 accent-rose-600" />Grades
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="competencies" class="h-4 w-4 accent-rose-600" />Competencies
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="notifications" class="h-4 w-4 accent-rose-600" />Notifications
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="announcements" class="h-4 w-4 accent-rose-600" />Announcements
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="attendance" class="h-4 w-4 accent-rose-600" />Attendance
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium dark:border-slate-700">
                            <input type="checkbox" data-reset-key="requirements" class="h-4 w-4 accent-rose-600" />Requirements
                        </label>
                    </div>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                        <p id="resetFeedback" class="text-xs text-slate-500"></p>
                        <button type="button" id="resetSelectedBtn"
                            class="rounded-xl bg-rose-600 px-5 py-2.5 font-semibold text-white hover:bg-rose-700">
                            Reset selected
                        </button>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <template id="auditTemplate">
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
            <div class="flex justify-between gap-2">
                <b data-audit-title class="text-sm"></b><time data-audit-date class="text-[11px] text-slate-400"></time>
            </div>
            <p data-audit-meta class="mt-1 text-xs text-slate-500"></p>
        </div>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/settings.js'])
</body>

</html>
