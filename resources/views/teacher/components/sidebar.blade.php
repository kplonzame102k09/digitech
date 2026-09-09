<aside id="side" class="sidebar fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 bg-white dark:bg-slate-900 border-r dark:border-slate-800">
    <div class="h-full flex flex-col">
        <div class="h-20 border-b dark:border-slate-800 flex items-center gap-3 px-5">
            <img src="{{ asset('images/16432.png') }}" class="w-10 h-10 rounded-xl object-cover" alt="Digitech College logo" />
            <div>
                <b>DIGITECH</b>
                <small class="block text-[10px] tracking-widest text-slate-400">
                    COLLEGE Portal
                </small>
            </div>
        </div>
        <nav class="p-3 space-y-1 flex-1">
            <a href="{{ route('teacher.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('teacher.dashboard') 
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="layout-dashboard" class="w-4"></i>
                Dashboard
            </a>
            <a href="{{ route('teacher.students') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
                {{ request()->routeIs('teacher.students') 
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="users" class="w-4"></i>
                Students
            </a>
            <a href="{{ route('teacher.grades') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
                {{ request()->routeIs('teacher.grades') 
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}"><i
                data-lucide="chart-no-axes-combined" class="w-4"></i>
                Grades
                <span data-nav-notif="grade" class="ml-auto hidden min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white"></span>
            </a>
            <a href="{{ route('teacher.competencies') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
                {{ request()->routeIs('teacher.competencies') 
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="award" class="w-4"></i>
                Competencies
                <span data-nav-notif="competency" class="ml-auto hidden min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white"></span>
            </a>
            <a href="{{ route('teacher.attendance') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
                {{ request()->routeIs('teacher.attendance')
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="calendar-check-2" class="w-4"></i>
                Attendance
                <span data-nav-notif="attendance" class="ml-auto hidden min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white"></span>
            </a>
            <a href="{{ route('teacher.announcements') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
                {{ request()->routeIs('teacher.announcements')
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="megaphone" class="w-4"></i>
                Announcements
                <span data-nav-notif="announcement" class="ml-auto hidden min-w-5 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white"></span>
            </a>
            <a href="{{ route('teacher.profile') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('teacher.profile')
                ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
                <i data-lucide="user-round" class="w-4"></i>
                Profile
            </a>
        </nav>
        <button type="button" data-logout onclick="AUTH.logout()"
            class="m-3 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
            <i data-lucide="log-out" class="w-4"></i>
            Log out
        </button>

    </div>
</aside>