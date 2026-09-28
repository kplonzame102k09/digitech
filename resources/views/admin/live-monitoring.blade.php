<!doctype html>
<html lang="en">

<head>
    @include('partials.meta', ['pageTitle' => 'Live Monitoring | Digitech College'])
    @include('partials.assets')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}" />
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    @include('admin.components.sidebar')
    <div class="lg:pl-64">
        @include('admin.components.header', ['title' => 'Live Monitoring', 'subtitle' => 'Administration'])
        <main class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-7xl">
                <section class="mb-2 p-6 sm:p-8">
                    <p class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                        Administration
                    </p>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-4">
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

                <section id="statsRow" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="card min-w-0 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Online now</p>
                        <p id="statOnlineNow" class="mt-2 break-words text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">active in the last 5 minutes</p>
                    </div>
                    <div class="card min-w-0 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Active (last hour)</p>
                        <p id="statActiveHour" class="mt-2 break-words text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">distinct signed-in users</p>
                    </div>
                    <div class="card min-w-0 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Requests (5 min)</p>
                        <p id="statRequests5m" class="mt-2 break-words text-3xl font-bold">-</p>
                        <p class="text-xs text-slate-400">tracked page &amp; API hits</p>
                    </div>
                    <div class="card min-w-0 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Security today</p>
                        <p id="statSecurityToday"
                            class="mt-2 break-words text-3xl font-bold text-red-600 dark:text-red-400">-</p>
                        <p class="text-xs text-slate-400">failed &amp; blocked logins</p>
                    </div>
                    <div class="card min-w-0 p-4 sm:col-span-2 xl:col-span-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Top IPs today</p>
                        <ul id="statTopIps" class="mt-2 space-y-1 text-sm"></ul>
                    </div>
                </section>

                <section id="mapCard" class="card mt-5 mb-5 overflow-hidden">
                    <style>
                        #mapCard { transition: width .18s ease, height .18s ease, top .18s ease, left .18s ease; }
                        #mapCard.dg-map-pop {
                            position: fixed !important;
                            /* Below the sticky header (z-30) and modal backdrops
                               (z-[90]) so it never overlaps them and gets
                               blurred along with the page when a modal opens. */
                            z-index: 20 !important;
                            display: flex;
                            flex-direction: column;
                            width: 460px;
                            height: 380px;
                            max-width: calc(100vw - 16px);
                            max-height: calc(100vh - 16px);
                            margin: 0;
                            top: 72px;
                            right: 16px;
                            isolation: isolate;
                            box-shadow: 0 12px 40px rgba(15, 23, 42, .35);
                            resize: both;
                        }
                        #mapCard.dg-map-pop #trafficMap { height: auto !important; flex: 1 1 0; min-height: 0; }
                        #mapCard.dg-map-grab #mapCardHeader { cursor: move; touch-action: none; user-select: none; }
                        #mapCard.dg-map-pop:fullscreen {
                            width: 100vw !important;
                            height: 100vh !important;
                            max-width: none !important;
                            max-height: none !important;
                            top: 0 !important;
                            left: 0 !important;
                            right: auto !important;
                            border-radius: 0;
                            resize: none;
                        }
                        #mapCard:fullscreen #trafficMap { height: 100% !important; flex: 1 1 0; }
                        .mapctrl {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 32px;
                            height: 32px;
                            border-radius: 8px;
                            border: 1px solid #e2e8f0;
                            color: #475569;
                            background: transparent;
                            cursor: pointer;
                            flex-shrink: 0;
                        }
                        .mapctrl:hover { background: #f1f5f9; }
                        .mapctrl svg { width: 16px; height: 16px; display: block; }
                        .dark .mapctrl { border-color: #334155; color: #cbd5e1; }
                        .dark .mapctrl:hover { background: #1e293b; }
                    </style>
                    <div id="mapCardHeader" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Users map
                        </h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-slate-400">online users pinned by GPS or IP · offline grouped at campus</span>
                            <button type="button" id="mapPopBtn" class="mapctrl" title="Pop out / dock the map" aria-label="Pop out / dock the map"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M15 5v14"/></svg></button>
                            <button type="button" id="mapFsBtn" class="mapctrl" title="Toggle fullscreen map" aria-label="Toggle fullscreen map"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 px-4 py-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#16a34a]"></span> online</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full border border-slate-400 bg-slate-200"></span> offline cluster</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#7c3aed]"></span> GPS opt-in</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full border border-slate-400 bg-[#b45309]"></span> registered address</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#0d9488]"></span> user-pinned</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-0.5 w-4 border-t-2 border-dashed border-slate-400"></span> recent trail</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#ef4444]"></span> failed sign-in</span>
                        <span class="text-slate-400 dark:text-slate-500">locations are approximate (device GPS · Wi-Fi/net · IP — hover a pin for its ±m accuracy)</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 px-4 py-2 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500">
                        <span>expected resolution: phone-Precise <b class="text-slate-500 dark:text-slate-400">±5–15 m</b></span>
                        <span>laptop/desktop Wi-Fi <b class="text-slate-500 dark:text-slate-400">~20–100 m</b></span>
                        <span>IP fallback <b class="text-slate-500 dark:text-slate-400">city/ISP (km)</b> — use user-pinned for the accurate spot</span>
                    </div>
                    <div id="trafficMap" class="h-96 w-full"></div>
                    <p id="mapEmpty" class="hidden p-4 text-center text-sm text-slate-400">
                        No users found for the selected role.
                    </p>
                </section>

                <div class="grid gap-5 xl:grid-cols-3 xl:items-start">
                    <section class="card xl:col-span-2">
                        <div class="sticky top-0 z-10 rounded-t-2xl border-b border-slate-100 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
                                <label class="sr-only" for="search">Search activity</label>
                                <input id="search" class="input w-full rounded border px-3 py-2 col-span-2 md:col-span-2 xl:col-span-2" placeholder="Search path, IP, or user" />
                                <select id="eventFilter" class="input w-full min-w-0 rounded border px-2 py-2" title="Event type">
                                    <option value="all">All events</option>
                                    <option value="security">Security only</option>
                                </select>
                                <select id="windowFilter" class="input w-full min-w-0 rounded border px-2 py-2" title="Lookback window">
                                    <option value="5">Last 5 minutes</option>
                                    <option value="15">Last 15 minutes</option>
                                    <option value="30" selected>Last 30 minutes</option>
                                    <option value="60">Last hour</option>
                                    <option value="360">Last 6 hours</option>
                                    <option value="1440">Last 24 hours</option>
                                </select>
                                <select id="typeFilter" class="input w-full min-w-0 rounded border px-2 py-2" title="Traffic type">
                                    <option value="all">All traffic</option>
                                    <option value="page">Pages only</option>
                                    <option value="api">API only</option>
                                </select>
                                <select id="roleFilter" class="input w-full min-w-0 rounded border px-2 py-2" title="Role">
                                    <option value="">All roles</option>
                                    <option value="admin">Admins</option>
                                    <option value="teacher">Teachers</option>
                                    <option value="student">Students</option>
                                    <option value="parent">Parents</option>
                                    <option value="guest">Guests</option>
                                </select>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] text-left text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="p-3">Time</th>
                                        <th class="p-3">User</th>
                                        <th class="p-3">Role</th>
                                        <th class="p-3">Event</th>
                                        <th class="p-3">Path</th>
                                        <th class="p-3">IP Address</th>
                                        <th class="p-3">Status</th>
                                        <th class="p-3 text-right">Actions</th>
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
                            class="hidden flex flex-col items-center justify-between gap-3 border-t border-slate-100 p-3 dark:border-slate-800 md:flex-row md:items-center">
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

                    <aside class="space-y-5 xl:col-span-1">
                        <section class="card overflow-hidden">
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

                        <section class="card overflow-hidden">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Locked accounts &amp; IPs
                                </h3>
                                <span id="lockedCount" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">0</span>
                            </div>
                            <div id="lockedTableContainer" class="overflow-x-auto hidden">
                                <table class="w-full min-w-[560px] text-left text-sm">
                                    <thead class="bg-slate-50 dark:bg-slate-800">
                                        <tr>
                                            <th class="p-3">Account/IP</th>
                                            <th class="p-3">Type</th>
                                            <th class="p-3">Locked at</th>
                                            <th class="p-3">Time remaining</th>
                                            <th class="p-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lockedAccountsList"></tbody>
                                </table>
                            </div>
                            <p id="lockedEmpty" class="p-6 text-center text-sm text-slate-400">
                                No accounts are currently locked.
                            </p>
                        </section>

                        <section class="card p-4">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Security controls
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                If a user contacts you about a blocked sign-in, clear the lock here, or block a source IP manually.
                            </p>
                            <div class="mt-3 grid gap-2 md:grid-cols-[1fr] md:items-center">
                                <input id="unlockIdentifier" class="input w-full rounded border px-3 py-2" placeholder="User ID or email (e.g. student-01)" />
                                <input id="unlockIp" class="input w-full rounded border px-3 py-2" placeholder="IP (optional)" />
                                <select id="unlockRole" class="input rounded border px-2 py-2">
                                    <option value="student">Student</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="admin">Admin</option>
                                    <option value="parent">Parent</option>
                                    <option value="guest">Guest</option>
                                </select>
                                <button type="button" id="unlockBtn"
                                    class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                                    Unlock
                                </button>
                            </div>
                            <p id="unlockMsg" class="mt-2 text-sm font-medium"></p>
                            <hr class="my-4 border-slate-100 dark:border-slate-800" />
                            <div class="flex flex-col gap-2 md:grid-cols-[1fr] sm:items-center">
                                <input id="lockIpInput" class="input w-full rounded border px-3 py-2" placeholder="IP address to block (e.g. 203.0.113.5)" />
                                <button type="button" id="lockIpBtn"
                                    class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-red-600 dark:text-white">
                                    Lock IP
                                </button>
                            </div>
                            <p id="lockIpMsg" class="mt-2 text-sm font-medium"></p>
                        </section>
                    </aside>
                </div>
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
            <div id="detailLock" class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-950 dark:bg-red-950/30">
                <p class="text-sm font-bold text-red-700 dark:text-red-300">Block this source</p>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                    Manually blocks this IP address from signing in until you unlock it here.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="detailLockIp"
                        class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900">
                        Lock IP
                    </button>
                </div>
                <p id="detailLockMsg" class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400"></p>
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
            <td class="p-3"><span data-row-time class="font-mono text-xs text-slate-500 dark:text-slate-400"></span></td>
            <td class="p-3"><b data-row-actor class="block font-medium"></b></td>
            <td class="p-3"><span data-row-role class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"></span></td>
            <td class="p-3"><span data-row-kind></span></td>
            <td class="max-w-xs p-3">
                <div class="flex items-center gap-2">
                    <span data-row-method class="font-mono text-xs font-bold text-slate-500 dark:text-slate-400"></span>
                    <span data-row-path class="truncate font-mono text-xs text-slate-600 dark:text-slate-300"></span>
                </div>
            </td>
            <td class="p-3">
                <span data-row-ip class="font-mono text-xs"></span>
                <span data-row-geo class="ml-1 text-xs text-slate-400"></span>
            </td>
            <td class="p-3"><span data-row-status class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold"></span></td>
            <td class="p-3">
                <div class="flex justify-end">
                    <button type="button" data-row-lock-ip
                        class="inline-flex items-center gap-1 rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/40"
                        title="Manually block this IP address" aria-label="Lock IP">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </button>
                </div>
            </td>
        </tr>
    </template>

    <template id="onlineTemplate">
        <li class="flex items-center justify-between gap-3 px-4 py-2.5">
            <div class="min-w-0">
                <p class="flex items-center gap-2 font-medium">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                    <span data-online-name class="truncate"></span>
                    <span data-online-id class="hidden shrink-0 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-500 dark:bg-slate-800 dark:text-slate-400"></span>
                </p>
                <p class="mt-0.5 truncate font-mono text-xs text-slate-400">
                    <span data-online-role class="font-sans"></span> &middot; <span data-online-ip></span>
                </p>
                <p class="font-mono text-xs">
                    <span data-online-phone></span><span data-online-dup class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"></span>
                </p>
            </div>
            <p class="shrink-0 text-xs text-slate-400"><span data-online-ago></span></p>
        </li>
    </template>

    <template id="lockedAccountTemplate">
        <tr class="border-t border-slate-100 transition-colors dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40">
            <td class="p-3">
                <span data-locked-identifier class="font-mono text-xs font-medium uppercase"></span>
            </td>
            <td class="p-3">
                <span data-locked-role class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"></span>
            </td>
            <td class="p-3">
                <span data-locked-time class="font-mono text-xs text-slate-500 dark:text-slate-400"></span>
            </td>
            <td class="p-3">
                <span data-locked-countdown class="font-mono text-xs font-bold text-red-600 dark:text-red-400"></span>
            </td>
            <td class="p-3">
                <button type="button" data-locked-unlock class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                    Unlock
                </button>
            </td>
        </tr>
    </template>
    <div id="modalRoot"></div>
    @include('partials.portal-scripts', ['portalPage' => 'admin/live-monitoring.js'])
</body>
</html>