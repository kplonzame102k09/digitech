<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>My Profile | Digitech College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-parent-page="profile" class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('parent.components.sidebar')
    <div class="lg:pl-64">
       @include('parent.components.header')
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl">
                <section class="student-hero mb-7 p-6 sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600"> Account settings </p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight"> Your parent profile </h2>
                    <p class="mt-2 max-w-2xl text-white"> Keep your contact information current for school communication
                        and
                        student support. </p>
                </section>
                <form id="profileForm" class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                    <section class="student-hero p-6 sm:p-7">
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="relative">
                                <div class="relative">
                                    <img id="profilePhoto" data-profile-photo class="h-28 w-28 rounded-3xl border-4 border-white object-cover shadow-xl"
                                        alt="Parent profile photo" />
                                    <label for="profilePhotoInput" class="absolute -bottom-2 -right-2 flex h-10 w-10 cursor-pointer items-center justify-center bg-green-700 rounded-2xl 
                                        text-white-700 shadow-lg transition hover:bg-emerald-50" title="Change profile photo">
                                        <i data-lucide="camera" class="h-4 w-4"></i>
                                    </label>
                                    <input id="profilePhotoInput" type="file" accept="image/jpeg,image/png,image/webp"
                                        class="sr-only" />
                                </div>
                                <span class="absolute bottom-1 right-1 h-4 w-4 rounded-full border-white"> </span>
                            </div>
                            <h3 id="profileName" class="mt-5 text-xl font-extrabold"></h3>
                            <p id="profileId" class="mt-1 font-mono text-xs text-emerald-100"></p>
                            <p id="profileRelationship" class="mt-2 text-sm text-white/75"></p>
                            <span class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">
                                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i> 
                                Parent account 
                            </span>
                        </div>
                        <div class="relative z-10 mt-8 border-t border-white/15 pt-5 text-sm text-white/80">
                            <div class="flex gap-3"> 
                                <i data-lucide="info" class="h-4 w-4 shrink-0"></i>
                                <p> Your parent ID and linked student records are managed by the college. </p>
                            </div>
                        </div>
                    </section>
                    <section class="card p-6 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700"> 
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                                <i data-lucide="contact"></i> 
                            </span>
                            <div>
                                <h3 class="font-extrabold">Contact information</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Update the information the school may use to reach you. 
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 grid gap-5 sm:grid-cols-2"> 
                            <label class="text-sm font-semibold sm:col-span-2">
                                Email address 
                                <input id="profileEmail" readonly class="input mt-1.5 w-full rounded-xl border bg-slate-50 px-3 py-3 dark:bg-slate-800" />
                            </label>
                            <label class="text-sm font-semibold"> 
                                Contact number 
                                <input id="profileContact" class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="e.g. +63 900 000 0000" />
                            </label> 
                            <label class="text-sm font-semibold"> 
                                Occupation 
                                <input id="profileOccupation" class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="Your occupation" /> 
                            </label>
                            <label class="text-sm font-semibold sm:col-span-2"> 
                                Emergency contact 
                                <input id="profileEmergencyContact" class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="Phone number or contact name" />
                            </label> 
                            <label class="text-sm font-semibold sm:col-span-2"> 
                                Home address 
                                <textarea id="profileAddress" rows="3" class="input mt-1.5 w-full resize-none rounded-xl border px-3 py-3" placeholder="House number, street, city"></textarea> 
                            </label>
                        </div>
                        <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                            <p class="text-xs text-slate-400"> 
                                <i data-lucide="info" class="mr-1 inline h-3.5 w-3.5"></i> 
                                Changes are  stored in your portal profile. 
                            </p> 
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
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
</body>

</html>