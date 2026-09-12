{{-- Unified profile page shell. Expects: $role ('admin'|'teacher'|'student'|'parent'|'guest'),
    $heroClass, $heroTitle, $heroIntro, $eyebrowClass, $saveButtonClass, $accentTextClass,
    $accentBtnIconClass, $accentCardIconClass, and an optional $headerTitle / $headerSubtitle.
    Per-role editable fields live in partials/profile-fields-{$role}. --}}
<!doctype html>
<html>

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
</head>

<body @if ($role === 'student') data-student-page="profile" @endif
    @if ($role === 'parent') data-parent-page="profile" @endif
    @if ($role === 'guest') data-guest-page="profile" @endif
    class="{{ $role === 'student' || $role === 'guest' ? 'student-portal ' : '' }}min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include($role.'.components.sidebar')
    <div class="lg:pl-64">
        @include($role.'.components.header', ['title' => $headerTitle ?? null, 'subtitle' => $headerSubtitle ?? null])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-5xl">
                <section class="{{ $heroClass }} mb-7 overflow-hidden rounded-3xl p-6 text-white shadow-xl sm:p-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] {{ $eyebrowClass }}">Account settings</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight">{{ $heroTitle }}</h2>
                    <p class="mt-2 max-w-2xl text-white">{{ $heroIntro }}</p>
                </section>

                <div class="mb-6 flex gap-1 rounded-2xl border border-slate-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900 sm:max-w-md">
                    <button type="button" data-profile-tab="details" aria-selected="true"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border-b-2 border-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-900 dark:border-white dark:text-white">
                        <i data-lucide="user-round" class="h-4 w-4"></i>Profile details
                    </button>
                    <button type="button" data-profile-tab="security" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border-b-2 border-transparent px-3 py-2.5 text-sm text-slate-500">
                        <i data-lucide="shield" class="h-4 w-4"></i>Security
                    </button>
                    <button type="button" data-profile-tab="activity" aria-selected="false"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl border-b-2 border-transparent px-3 py-2.5 text-sm text-slate-500">
                        <i data-lucide="activity" class="h-4 w-4"></i>Activity
                    </button>
                </div>

                <section data-profile-panel="details">
                    <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                        <section class="p-6 sm:p-7">
                            <div class="flex flex-col items-center text-center">
                                <div class="relative">
                                    <img id="bigAvatar" data-profile-photo
                                        class="h-28 w-28 rounded-3xl border-4 border-white object-cover shadow-xl dark:border-slate-800"
                                        alt="{{ ucfirst($role) }} profile photo" />
                                    <label for="profilePhotoInput"
                                        class="absolute -bottom-2 -right-2 inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white shadow-lg transition {{ $accentTextClass }} hover:bg-slate-50 focus-within:ring-2 focus-within:ring-white focus-within:ring-offset-2 focus-within:ring-offset-slate-800"
                                        title="Change profile photo">
                                        <i data-lucide="camera" class="h-4 w-4"></i>
                                        <span class="sr-only">Change profile photo</span>
                                    </label>
                                    <input id="profilePhotoInput" type="file" accept="image/jpeg,image/png,image/webp"
                                        class="sr-only" />
                                </div>
                                <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">JPG, PNG, or WebP up to 2 MB</p>
                                <h3 id="profileName" class="mt-5 text-xl font-extrabold"></h3>
                                <p id="profileRole" class="mt-1 text-sm {{ $accentTextClass }}"></p>
                                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                    <span id="profileStatus"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <i data-lucide="badge-check" class="h-3.5 w-3.5"></i><span>Active</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                        <i data-lucide="calendar" class="h-3.5 w-3.5"></i>Member since <span id="memberSince">—</span>
                                    </span>
                                </div>
                                <div class="mt-5 grid w-full gap-2 sm:max-w-xs">
                                    <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                                        <i data-lucide="mail" class="h-4 w-4 shrink-0 text-slate-400"></i>
                                        <span id="profileEmailQuick" class="truncate text-slate-600 dark:text-slate-300">—</span>
                                    </div>
                                    <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                                        <i data-lucide="phone" class="h-4 w-4 shrink-0 text-slate-400"></i>
                                        <span id="profilePhoneQuick" class="truncate text-slate-600 dark:text-slate-300">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-7 border-t border-slate-200 pt-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                <div class="flex gap-3"><i data-lucide="shield-check" class="h-4 w-4 shrink-0"></i>
                                    <p>Your official identity and email are managed by the college.</p>
                                </div>
                            </div>
                        </section>

                        <form id="profileForm" class="card p-6 sm:p-7">
                            <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl {{ $accentCardIconClass }}">
                                    <i data-lucide="contact"></i>
                                </span>
                                <div>
                                    <h3 class="font-extrabold">Profile information</h3>
                                    <p class="mt-1 text-sm text-slate-500">Update the information the school may use to
                                        reach you.</p>
                                </div>
                            </div>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                @include('partials.profile-fields-'.$role)
                            </div>
                            <div
                                class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                                <p class="text-xs text-slate-400"><i data-lucide="info"
                                        class="mr-1 inline h-3.5 w-3.5"></i>Changes are saved to your portal profile.</p>
                                <button type="submit" data-profile-save
                                    class="inline-flex items-center gap-2 rounded-xl {{ $saveButtonClass }} px-5 py-3 text-sm font-semibold text-white shadow-sm transition"><i
                                        data-lucide="save" class="h-4 w-4"></i>Save changes</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section data-profile-panel="security" class="hidden">
                    <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                        <section class="card sticky top-6 p-6 sm:p-7">
                            <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300">
                                    <i data-lucide="key-round"></i>
                                </span>
                                <div>
                                    <h3 class="font-extrabold">Account security</h3>
                                    <p class="mt-1 text-sm text-slate-500">Keep your account safe with a strong,
                                        unique password.</p>
                                </div>
                            </div>
                            <ul class="mt-6 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                <li class="flex items-center gap-3"><i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-500"></i>At least 12 characters</li>
                                <li class="flex items-center gap-3"><i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-500"></i>Different from your current password</li>
                                <li class="flex items-center gap-3"><i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-500"></i>You stay signed in after changing it</li>
                            </ul>
                            <div class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                <div class="flex gap-3">
                                    <i data-lucide="lightbulb" class="mt-0.5 h-4 w-4 shrink-0"></i>
                                    <p>If you forget your password, contact the school administrator to reset it.</p>
                                </div>
                            </div>
                        </section>

                        <form id="passwordForm" class="card p-6 sm:p-7">
                            <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                                <span
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300">
                                    <i data-lucide="lock"></i>
                                </span>
                                <div>
                                    <h3 class="font-extrabold">Change password</h3>
                                    <p class="mt-1 text-sm text-slate-500">Enter your current password first.</p>
                                </div>
                            </div>
                            <div id="passwordError" role="alert"
                                class="mb-5 hidden rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"></div>
                            <div class="mt-6 grid gap-5">
                                <label class="text-sm font-semibold">Current password
                                    <input id="currentPassword" type="password" autocomplete="current-password"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="Your current password" />
                                </label>
                                <label class="text-sm font-semibold">New password
                                    <input id="newPassword" type="password" autocomplete="new-password"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="At least 12 characters" />
                                </label>
                                <label class="text-sm font-semibold">Confirm new password
                                    <input id="newPasswordConfirm" type="password" autocomplete="new-password"
                                        class="input mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="Repeat the new password" />
                                </label>
                            </div>
                            <div
                                class="mt-7 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                                <p class="text-xs text-slate-400"><i data-lucide="info"
                                        class="mr-1 inline h-3.5 w-3.5"></i>Password changes are also recorded in your activity log.</p>
                                <button id="changePasswordBtn" type="submit"
                                    class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-purple-700"><i
                                        data-lucide="shield-check" class="h-4 w-4"></i>Update password</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section data-profile-panel="activity" class="hidden">
                    <div class="card p-6 sm:p-7">
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-5 dark:border-slate-700">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">
                                <i data-lucide="activity"></i>
                            </span>
                            <div>
                                <h3 class="font-extrabold">Recent activity</h3>
                                <p class="mt-1 text-sm text-slate-500">Sign-ins, password changes, and profile edits on
                                    your account.</p>
                            </div>
                        </div>
                        <div id="activityList" class="mt-6 space-y-3"></div>
                        <div id="activityEmpty" class="hidden mt-6 flex flex-col items-center py-10 text-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800">
                                <i data-lucide="inbox" class="h-6 w-6 text-slate-400"></i>
                            </span>
                            <p class="mt-4 font-semibold text-slate-600 dark:text-slate-300">No activity yet</p>
                            <p class="mt-1 text-sm text-slate-400">Sign-ins and changes will appear here.</p>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'profile.js'])
    @include('partials.password-toggle')
</body>

</html>