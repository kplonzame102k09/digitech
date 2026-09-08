<aside id="side"
    class="sidebar fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">
    <div class="flex h-full flex-col">
        <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
            <img src="{{ asset('images/16432.png') }}" class="h-10 w-10 rounded-xl object-cover"
                alt="Digitech College logo" />
            <div>
                <div class="font-extrabold tracking-tight">DIGITECH</div>
                <div class="text-[10px] font-semibold tracking-[.22em] text-slate-400">
                    COLLEGE PORTAL
                </div>
            </div>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            <a href="{{ route('parent.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.dashboard')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="layout-dashboard" class="w-4"></i>
                Dashboard
            </a>
            <a href="{{ route('parent.children') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.children')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="users-round" class="w-4"></i>
                My Children
            </a>
            <a href="{{ route('parent.attendance') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.attendance')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="calendar-check-2" class="w-4"></i>
                Attendance
            </a>
            <a href="{{ route('parent.grades') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.grades')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="chart-no-axes-combined" class="w-4"></i>
                Grades
            </a>
            <a href="{{ route('parent.documents') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.documents')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="file-text" class="w-4"></i>
                Documents
            </a>
            <a href="{{ route('parent.announcements') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.announcements')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="megaphone" class="w-4"></i>
                Announcements
            </a>
            <a href="{{ route('parent.profile') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('parent.profile')
                ? 'nav-active' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'}}">
                <i data-lucide="user-round" class="w-4"></i>
                Profile
            </a>
        </nav>
        <div class="border-t border-slate-200 p-3 dark:border-slate-800">
            <button type="button" data-logout
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                <i data-lucide="log-out" class="h-4 w-4"></i>Log out
            </button>
        </div>
    </div>
</aside>
