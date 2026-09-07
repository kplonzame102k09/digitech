<aside id="side"
    class="sidebar fixed inset-y-0 left-0 z-50 w-64 -translate-x-full transform border-r border-slate-200 bg-white transition-transform duration-300 ease-in-out lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">

    <div class="flex h-full flex-col">

        {{-- LOGO --}}
        <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
            <img
                src="{{ asset('assets/images/16432.png') }}"
                class="h-10 w-10 rounded-xl object-cover"
                alt="Digitech College logo">

            <div>
                <div class="font-extrabold tracking-tight">
                    DIGITECH
                </div>

                <div class="text-[10px] font-semibold tracking-[.22em] text-slate-400">
                    COLLEGE PORTAL
                </div>
            </div>
        </div>

        {{-- NAVIGATION --}}
        <nav class="flex-1 space-y-1 overflow-y-auto p-3">

            {{-- Dashboard --}}
            <a
                href="{{ route('admin.dashboard') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.dashboard')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="layout-dashboard" class="w-4"></i>
                Dashboard
            </a>

            {{-- Users --}}
            <a
                href="{{ route('admin.users') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.users')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="users" class="w-4"></i>
                Users
            </a>

            {{-- Enrollment --}}
            <a
                href="{{ route('admin.enrollment') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.enrollment')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="clipboard-list" class="w-4"></i>
                Enrollment
            </a>

            {{-- Documents --}}
            <a
                href="{{ route('admin.documents') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.documents')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="file-text" class="w-4"></i>
                Documents
            </a>

            {{-- Grades --}}
            <a
                href="{{ route('admin.grades') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.grades')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="chart-no-axes-combined" class="w-4"></i>
                Grades
            </a>

            {{-- Competencies --}}
            <a
                href="{{ route('admin.competencies') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.competencies')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="award" class="w-4"></i>
                Competencies
            </a>

            {{-- Attendance --}}
            <a
                href="{{ route('admin.attendance') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.attendance')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="calendar-check-2" class="w-4"></i>
                Attendance
            </a>

            {{-- Requirements --}}
            <a
                href="{{ route('admin.requirements') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.requirements')
                    ? 'nav-active'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <i data-lucide="folder-check" class="w-4"></i>
                Requirements
            </a>

            {{-- Announcements --}}
            <a
                href="{{ route('admin.announcements') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.announcements')
                    ? 'nav-active'
                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-800' }}">
                <i data-lucide="megaphone" class="w-4"></i>
                Announcements
            </a>

            {{-- Parent Links --}}
            <a
                href="{{ route('admin.parent-links') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.parent-links')
                    ? 'nav-active'
                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-800' }}">
                <i data-lucide="users-round" class="w-4"></i>
                Parent links
            </a>

            {{-- Settings --}}
            <a
                href="{{ route('admin.settings') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium
                {{ request()->routeIs('admin.settings')
                    ? 'nav-active'
                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-800' }}">
                <i data-lucide="settings" class="w-4"></i>
                Settings
            </a>

        </nav>

        {{-- LOGOUT --}}
        <button
            type="button"
            data-logout
            class="m-3 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">

            <i data-lucide="log-out" class="h-4 w-4"></i>
            Log out
        </button>

    </div>
</aside>
```
