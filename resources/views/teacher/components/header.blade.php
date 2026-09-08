<header class="sticky top-0 z-30 h-16 border-b bg-white/90 dark:bg-slate-950/90 dark:border-slate-800 backdrop-blur flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button id="open" class="lg:hidden p-2">
            <i data-lucide="menu"></i>
        </button>
        <div>
            <p class="text-xs text-slate-400"> {{ $subtitle ?? 'Teacher Portal' }} </p>
            <h1 class="font-bold"> {{ $title ?? 'Teacher Dashboard' }} </h1>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="APP.toggleTheme()" class="p-2.5 rounded-xl border dark:border-slate-700">
            <i data-lucide="moon" data-theme-icon class="h-4 w-4"></i>
        </button>
        <button onclick="APP.showNotifications()" class="p-2.5 rounded-xl border dark:border-slate-700">
            <i data-lucide="bell" class="h-4 w-4"></i>
            <span id="topNotif"
                class="absolute -right-1 -top-1 hidden h-4 min-w-4 rounded-full bg-red-500 px-1 text-[9px] font-bold text-white"></span>
        </button>
        <img id="avatar" data-profile-photo class="w-9 h-9 rounded-full object-cover border-green-100" alt="Profile" />
    </div>
</header>