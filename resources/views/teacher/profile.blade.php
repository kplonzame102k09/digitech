<!doctype html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Teacher Profile | Digitech College</title>
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
            <div class="mx-auto max-w-5xl">
                <section class="teacher-hero mb-7 overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">Account settings</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight">Your teacher profile</h2>
                    <p class="mt-2 max-w-2xl text-white">
                        Keep your faculty details current so students and administrators see
                        the right contact information.
                    </p>
                </section>
                <form id="profileForm" class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <section class="teacher-profile-card card p-6 sm:p-7 student-hero">
                        <div class="flex flex-col items-center text-center">
                            <div class="relative">
                                <img id="av" data-profile-photo class="h-28 w-28 rounded-3xl border-4 border-white object-cover 
                                    shadow-xl dark:border-slate-800" alt="Teacher profile photo" />
                                <label for="profilePhotoInput" class="absolute -bottom-2 -right-2 flex h-10 w-10 cursor-pointer items-center justify-center bg-green-700 
                                    rounded-2xl text-white-700 shadow-lg transition hover:bg-emerald-50"
                                    title="Change profile photo">
                                    <i data-lucide="camera" class="h-4 w-4"></i>
                                </label>
                                <input id="profilePhotoInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" />
                            </div>
                            <h3 id="nm" class="mt-5 text-xl font-extrabold text-white"></h3>
                            <p id="id" class="mt-1 font-mono text-xs text-white dark:text-emerald-300"></p>
                            <p id="dept" class="mt-2 text-sm text-white"></p>
                        </div>
                        <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
                        </div>
                        <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                            <div class="flex gap-3">
                                <i data-lucide="lock-keyhole" class="mt-0.5 h-4 w-4 shrink-0"></i>
                                <p>Your account identity and email are managed by the school administrator.</p>
                            </div>
                        </div>
                    </section>
                    <section class="card p-6 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="id-card"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Faculty information</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Some fields are read-only to protect your
                                    official identity.
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <label class="text-sm font-semibold">
                                Last name
                                <input id="lastName" readonly class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3 dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold">
                                First name
                                <input id="firstName" readonly class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3 dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold sm:col-span-2">
                                Email address
                                <input id="email" readonly class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-4 py-3 dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold sm:col-span-2">
                                Subject, strand, or specialization
                                <input id="specialization" maxlength="120" class="input mt-1.5 w-full rounded-xl border px-4 py-3" placeholder="e.g. ICT / Computer Programming" />
                            </label>
                            <label class="text-sm font-semibold">
                                Phone number
                                <input id="phone" type="tel" maxlength="30" class="input mt-1.5 w-full rounded-xl border px-4 py-3" placeholder="e.g. +63 900 000 0000" />
                            </label>
                        </div>
                        <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                            <p class="text-xs text-slate-400">
                                <i data-lucide="check-circle-2" class="mr-1 inline h-3.5 w-3.5 text-emerald-500"></i>
                                Changes are saved to your portal profile.
                            </p>
                            <button id="saveProfile" type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                <i data-lucide="save" class="h-4 w-4"></i>
                                Save changes
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
@include('partials.portal-scripts', ['portalPage' => 'teacher/teacher.js'])
</body>

</html>