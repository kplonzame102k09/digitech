<!doctype html>
<html lang="en">

<head>
    @include('partials.meta', ['pageTitle' => 'Live Monitoring | Digitech College'])
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header', ['title' => 'Live Monitoring', 'subtitle' => 'Realtime portal traffic and activity'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-2 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                        Administration
                    </p>
                    <div class="mt-1 flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight">
                                Live Monitoring
                            </h2>
                            <p class="mt-2 text-slate-500">
                                Realtime requests, IP addresses, and who is online right now.
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span id="livePulse" class="inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-700 dark:bg-green-950/40 dark:text-green-300">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                </span>
                                LIVE
                            </span>
                            <button type="button" id="pauseBtn"
                                class="rounded border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                Pause
                            </button>
                        </div>
                    </div>
                </section>

                <section id="statsRow" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Online now</p>
                        <p id="statOnlineNow" class="mt-2 text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">active in the last 5 minutes</p>
                    </div>
                    <div class="card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Active (last hour)</p>
                        <p id="statActiveHour" class="mt-2 text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">distinct signed-in users</p>
                    </div>
                    <div class="card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Requests (5 min)</p>
                        <p id="statRequests5m" class="mt-2 text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">tracked page &amp; API hits</p>
                    </div>
                    <div class="card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Security today</p>
                        <p id="statSecurityToday"
                            class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">-</p>
                        <p class="text-xs text-slate-400">failed &amp; blocked logins</p>
                    </div>
                    <div class="card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Top IPs today</p>
                        <ul id="statTopIps" class="mt-2 space-y-1 text-sm"></ul>
                    </div>
                </section>

                <section id="mapCard" class="card mt-5 hidden overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Live traffic map
                        </h3>
                        <span class="text-xs text-slate-400">top IPs today + online now</span>
                    </div>
                    <div id="trafficMap" class="h-96 w-full"></div>
                    <p id="mapEmpty" class="p-4 text-center text-sm text-slate-400">
                        Waiting for IP locations to resolve…
                    </p>
                </section>

                <section class="card mt-5 p-4">
                    <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto_auto_auto] md:items-center">
                        <label class="sr-only" for="search">Search activity</label>
                        <input id="search" class="input w-full rounded border px-3 py-3" placeholder="Search by path, IP, or user name" />
                        <select id="eventFilter" class="input rounded border px-3 py-3" title="Event type">
                            <option value="all">All events</option>
                            <option value="security">Security only</option>
                        </select>
                        <select id="windowFilter" class="input rounded border px-3 py-3" title="Lookback window">
                            <option value="5">Last 5 minutes</option>
                            <option value="15">Last 15 minutes</option>
                            <option value="30" selected>Last 30 minutes</option>
                            <option value="60">Last hour</option>
                            <option value="360">Last 6 hours</option>
                            <option value="1440">Last 24 hours</option>
                        </select>
                        <select id="typeFilter" class="input rounded border px-3 py-3" title="Traffic type">
                            <option value="all">All traffic</option>
                            <option value="page">Pages only</option>
                            <option value="api">API only</option>
                        </select>
                        <select id="roleFilter" class="input rounded border px-3 py-3" title="Role">
                            <option value="">All roles</option>
                            <option value="admin">Admins</option>
                            <option value="teacher">Teachers</option>
                            <option value="student">Students</option>
                            <option value="parent">Parents</option>
                            <option value="guest">Guests</option>
                        </select>
                    </div>
                </section>

                <section class="card mt-5 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="p-4">Time</th>
                                    <th class="p-4">User</th>
                                    <th class="p-4">Role</th>
                                    <th class="p-4">Event</th>
                                    <th class="p-4">Path</th>
                                    <th class="p-4">IP Address</th>
                                    <th class="p-4">Status</th>
                                </tr>
                            </thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                    <div id="emptyState" class="hidden p-10 text-center">
                        <i data-lucide="radio" class="mx-auto h-8 w-8 text-slate-300"></i>
                        <h3 class="mt-3 font-semibold">No activity recorded yet</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Activity is captured as users browse the portal. Check back in a moment.
                        </p>
                    </div>
                    <div id="tablePagination"
                        class="hidden flex flex-col items-center justify-between gap-3 border-t border-slate-100 p-4 dark:border-slate-800 md:flex-row md:items-center">
                        <div class="flex flex-wrap items-center gap-3">
                            <label for="pageSize" class="text-xs font-medium text-slate-500 dark:text-slate-400">Rows</label>
                            <select id="pageSize" class="input rounded border px-2 py-1.5 text-xs" title="Rows per page">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <span id="tableInfo" class="text-xs text-slate-500 dark:text-slate-400"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="pageLabel" class="text-xs font-medium text-slate-500 dark:text-slate-400"></span>
                            <button type="button" id="pagePrev"
                                class="rounded border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                Prev
                            </button>
                            <button type="button" id="pageNext"
                                class="rounded border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                Next
                            </button>
                        </div>
                    </div>
                </section>

                <section class="card mt-5 p-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Manually unlock a locked account
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        If a user contacts you about a blocked sign-in, clear their lock here so they can log in again right away.
                    </p>
                    <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto_auto_auto] md:items-center">
                        <input id="unlockIdentifier" class="input w-full rounded border px-3 py-3" placeholder="User ID or email (e.g. student-01)" />
                        <select id="unlockRole" class="input rounded border px-3 py-3">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Admin</option>
                            <option value="parent">Parent</option>
                            <option value="guest">Guest</option>
                        </select>
                        <input id="unlockIp" class="input w-full rounded border px-3 py-3 md:w-48" placeholder="IP (optional)" />
                        <button type="button" id="unlockBtn"
                            class="rounded bg-red-600 px-5 py-3 text-sm font-semibold text-white hover:bg-red-700">
                            Unlock
                        </button>
                    </div>
                    <p id="unlockMsg" class="mt-2 text-sm font-medium"></p>
                </section>

                <section class="card mt-5 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Online now
                        </h3>
                        <span id="onlineCount" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold dark:bg-slate-800">0</span>
                    </div>
                    <ul id="onlineList" class="divide-y divide-slate-100 dark:divide-slate-800"></ul>
                    <p id="onlineEmpty" class="p-6 text-center text-sm text-slate-400">
                        No signed-in users active in the last 5 minutes.
                    </p>
                </section>
            </div>
        </main>
    </div>

    <!-- Activity Detail Dialog -->
    <dialog id="detailDialog" class="w-[min(600px,calc(100%-2rem))] rounded-2xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <section class="card p-6 dark:text-white">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-purple-600 dark:text-purple-400">Activity details</p>
                    <h3 id="detailPath" class="mt-1 break-all text-lg font-bold uppercase"></h3>
                </div>
                <button type="button" data-close-detail class="rounded p-2 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div id="detailBody" class="mt-6 grid gap-3 sm:grid-cols-2"></div>
            <div id="detailUnlock" class="mt-6 hidden rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-950 dark:bg-red-950/30">
                <p class="text-sm font-bold text-red-700 dark:text-red-300">Lift sign-in lock</p>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                    Clears the brute-force lock so the affected user can log in immediately.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="detailUnlockAccount"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Unlock account
                    </button>
                    <button type="button" id="detailUnlockIp"
                        class="rounded border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/50">
                        Unlock IP
                    </button>
                </div>
                <p id="detailUnlockMsg" class="mt-2 text-xs font-medium text-green-700 dark:text-green-300"></p>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" data-close-detail
                    class="rounded bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">
                    Close
                </button>
            </div>
        </section>
    </dialog>

    <template id="rowTemplate">
        <tr class="border-t border-slate-100 transition-colors dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40">
            <td class="p-4"><span data-row-time class="font-mono text-xs text-slate-500 dark:text-slate-400"></span></td>
            <td class="p-4"><b data-row-actor class="block font-medium"></b></td>
            <td class="p-4"><span data-row-role class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"></span></td>
            <td class="p-4"><span data-row-kind></span></td>
            <td class="max-w-xs p-4">
                <div class="flex items-center gap-2">
                    <span data-row-method class="font-mono text-xs font-bold text-slate-500 dark:text-slate-400"></span>
                    <span data-row-path class="truncate font-mono text-xs text-slate-600 dark:text-slate-300"></span>
                </div>
            </td>
            <td class="p-4">
                <span data-row-ip class="font-mono text-xs"></span>
                <span data-row-geo class="ml-1 text-xs text-slate-400"></span>
            </td>
            <td class="p-4"><span data-row-status class="font-mono text-xs font-bold"></span></td>
        </tr>
    </template>

    <template id="onlineTemplate">
        <li class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
                <p class="flex items-center gap-2 font-medium">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                    <span data-online-name class="truncate"></span>
                </p>
                <p class="mt-0.5 truncate font-mono text-xs text-slate-400">
                    <span data-online-role class="font-sans"></span> &middot; <span data-online-ip></span>
                </p>
            </div>
            <p class="shrink-0 text-xs text-slate-400"><span data-online-ago></span></p>
        </li>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/live-monitoring.js'])
</body>
</html>