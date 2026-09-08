<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
    <div class="flex h-16 items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <button id="open" class="lg:hidden p-2">
                <i data-lucide="menu"></i>
            </button>
            <div>
                <p class="text-xs text-slate-400"> {{ $subtitle ?? 'Parent Portal' }} </p>
                <h1 class="font-bold"> {{ $title ?? 'Parent Dashboard' }} </h1>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" data-theme-toggle
                class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                <i data-theme-icon data-lucide="moon" class="h-4 w-4"></i>
            </button>
            <button type="button" data-notifications
                class="relative rounded-xl border border-slate-200 p-2.5 dark:border-slate-700">
                <i data-lucide="bell" class="h-4 w-4"></i>
                <span id="topNotif"
                    class="absolute -right-1 -top-1 hidden h-4 min-w-4 rounded-full bg-red-500 px-1 text-[9px] font-bold text-white"></span>
            </button>
            <img id="profilePhotoInput" data-profile-photo src="{{ asset('images/16432.png') }}"
                class="ml-1 h-9 w-9 rounded-full object-cover border-green-100" alt="Parent profile photo" />
            <div class="hidden sm:block">
                <div class="text-sm font-semibold" data-parent-full-name></div>
                <div class="text-[11px] text-slate-400" data-parent-id></div>
            </div>
        </div>
    </div>
</header>