<aside id="side" class="sidebar fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 bg-white dark:bg-slate-900 border-r dark:border-slate-800">
    <div class="h-full flex flex-col">
      <div class="h-20 border-b dark:border-slate-800 flex items-center gap-3 px-5">
        <img src="../assets/images/16432.png" class="w-10 h-10 rounded-xl object-cover" alt="Digitech College logo" />
        <div>
          <b>DIGITECH</b><small class="block text-[10px] tracking-widest text-slate-400">COLLEGE PORTAL</small>
        </div>
      </div>
      <nav class="p-3 space-y-1 flex-1">
        <a href="{{ route('student.dashboard') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.dashboard')
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="layout-dashboard" class="w-4"></i>
            Dashboard
        </a>
        <a href="{{ route('student.attendance') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.attendance')
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="clipboard-list" class="w-4"></i>
            Enrollment
        </a>
        <a href="{{ route('student.requirements') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.requirements') 
          ? 'nav-ative' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="folder-check" class="w-4"></i>
            Requirements
        </a>
        <a href="{{ route('student.documents') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.documents') 
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="file-text" class="w-4"></i>
            Documents
        </a>
        <a href="{{ route('student.grades') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.grades') 
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="chart-no-axes-combined" class="w-4"></i>
            Grades
        </a>
        <a href="{{ route('student.comptencies') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.competencies') 
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}"><i
            data-lucide="award" class="w-4"></i>
            Competencies
        </a>
        <a href="{{ route('student.enrollment') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.enrollment') 
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="calendar-check-2" class="w-4"></i>
            Attendance
        </a>
        <a href="{{ route('student.announcements') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.announcements') 
          ? 'nav-active' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'}}">
          <i data-lucide="megaphone" class="w-4"></i>
            Announcements
        </a>
        <a href="{{ route('student.profile') }}"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium 
          {{ request()->routeIs('student.profile') 
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