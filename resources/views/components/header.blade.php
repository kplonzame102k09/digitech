<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
    <div class="flex h-16 items-center justify-between px-4 sm:px-6"> 
        {{-- LEFT SIDE --}} 
        <div class="flex items-center gap-3"> 
            {{-- Mobile Sidebar Button --}} 
            <button id="open" type="button" class="rounded-lg p-2 lg:hidden"> 
                <i data-lucide="menu" class="h-5 w-5"></i> 
            </button> 
            {{-- PAGE TITLE --}} 
            <div>
                <p class="text-xs text-slate-400"> {{ $subtitle ?? 'Admin Portal' }} </p>
                <h1 class="font-bold"> {{ $title ?? 'Admin Dashboard' }} </h1>
            </div>
        </div> 
        {{-- RIGHT SIDE --}} 
        <div class="flex items-center gap-2"> 
            {{-- THEME TOGGLE --}} 
            <button type="button" data-theme-toggle class="rounded-xl border border-slate-200 p-2.5 dark:border-slate-700"> 
                <i data-lucide="moon" data-theme-icon class="h-4 w-4"> </i> 
            </button> 
            {{-- NOTIFICATIONS --}} 
            <button type="button" data-notifications class="relative rounded-xl border border-slate-200 p-2.5 dark:border-slate-700"> 
                <i data-lucide="bell" class="h-4 w-4"> </i> 
                <span id="topNotif" class="fixed hidden w-4 rounded-full bg-red-500 px-1 text-[9px] font-bold text-white"> </span>
            </button> 
            {{-- PROFILE PHOTO --}} 
            <img id="avatar" data-profile-photo src="{{ asset('assets/images/16432.png') }}" class="h-9 w-9 rounded-full object-cover"
                alt="Admin profile photo"> 
            </div>
    </div>
</header>