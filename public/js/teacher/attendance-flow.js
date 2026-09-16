(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (value) => {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  const photoUrl = (value) => {
    if (window.DG && typeof DG.normalizePhotoUrl === 'function') return DG.normalizePhotoUrl(value);
    if (!value) return '/images/16432.png';
    return /^(?:https?:|data:)/.test(value) || value.startsWith('/') ? value : `/storage/${value.replace(/^\/+/, '')}`;
  };

  const API_BASE = '/teacher/api/attendance';
  const ME = window.PORTAL_USER_ID;

  const state = {
    allClassrooms: [],
    selection: { year: '', department: '', section: '', subject: '' },
    classroom: null,
    session: null,
  };

  let pollTimer = null;

  const app = () => $('app');

  const showError = (message) => {
    const el = $('appError');
    el.textContent = message;
    el.classList.remove('hidden');
  };
  const clearError = () => $('appError').classList.add('hidden');

  const quotes = (value) => `"${esc(value)}"`;

  const todayISO = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
  };

  const nowHM = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(now.getHours())}:${pad(now.getMinutes())}`;
  };

  const statusChip = (status) => {
    const map = {
      Present: 'emerald',
      Late: 'amber',
      Excused: 'violet',
      Absent: 'rose',
    };
    const color = map[status] || 'slate';
    return `<span class="inline-flex items-center gap-1 rounded-full bg-${color}-100 px-2.5 py-0.5 text-xs font-semibold text-${color}-700 dark:bg-${color}-500/10 dark:text-${color}-300">${esc(status)}</span>`;
  };

  const summaryChip = (label, value, color) => `
    <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-900">
      <div class="text-2xl font-extrabold text-${color}-600 dark:text-${color}-400">${value}</div>
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">${label}</div>
    </div>`;

  const render = (html) => {
    clearError();
    app().innerHTML = html;
    if (window.lucide) lucide.createIcons();
  };

  // ---------------------------------------------------------------- bootstrap

  const init = async () => {
    APP?.applyTheme?.();
    document.querySelectorAll('[data-theme-toggle]').forEach((button) =>
      button.addEventListener('click', () => APP?.toggleTheme?.())
    );
    try {
      const data = await API.request(`${API_BASE}/classrooms`);
      state.allClassrooms = data.classrooms || [];
    } catch (error) {
      showError(error.message);
    }
    renderPicker();
  };

  const distinct = (classes, key) =>
    [...new Set(classes.map((c) => c[key]).filter((v) => v !== null && String(v).trim() !== ''))].sort();

  const available = (key) => {
    let list = state.allClassrooms;
    if (state.selection.year) list = list.filter((c) => c.academicYear === state.selection.year);
    if (state.selection.department) list = list.filter((c) => c.department === state.selection.department);
    if (state.selection.section) list = list.filter((c) => c.section === state.selection.section);
    return distinct(list, key);
  };

  const resetDependents = (level) => {
    if (level <= 1) state.selection.department = '';
    if (level <= 2) state.selection.section = '';
    if (level <= 3) state.selection.subject = '';
  };

  const filteredClassrooms = () =>
    state.allClassrooms.filter((c) =>
      [['year', 'academicYear'], ['department', 'department'], ['section', 'section'], ['subject', 'subject']]
        .every(([sel, key]) => !state.selection[sel] || c[key] === state.selection[sel])
    );

  // ---------------------------------------------------------------- picker

  const selectBlock = (id, label, icon, key, values, placeholder) => `
    <label class="block">
      <span class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300">
        <i data-lucide="${icon}" class="h-4 w-4 text-blue-500"></i>${label}
      </span>
      <select id="${id}" class="input w-full">
        <option value="">${placeholder}</option>
        ${values.map((v) => `<option value="${esc(v)}" ${state.selection[key] === v ? 'selected' : ''}>${esc(v)}</option>`).join('')}
      </select>
    </label>`;

  const renderPicker = (focusClassroomId = null) => {
    render(`
      <div class="grid gap-5">
        <section class="card p-6">
          <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold">Narrow down a classroom</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400">Each picker only shows options that still have classrooms below it.</p>
            </div>
            <span class="text-xs font-semibold text-slate-400">${state.allClassrooms.length} classroom${state.allClassrooms.length === 1 ? '' : 's'} available</span>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            ${selectBlock('filterYear', 'Academic year', 'calendar', 'year', available('academicYear'), 'All years')}
            ${selectBlock('filterDepartment', 'Department', 'building-2', 'department', available('department'), 'All departments')}
            ${selectBlock('filterSection', 'Section', 'users', 'section', available('section'), 'All sections')}
            ${selectBlock('filterSubject', 'Subject', 'book-open', 'subject', available('subject'), 'All subjects')}
          </div>
        </section>

        <section class="card p-6">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-bold">Classrooms</h3>
            <button id="resetFilters" class="text-sm font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">Reset filters</button>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" id="classroomGrid">${classroomCards()}</div>
        </section>

        <section class="card p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold">Past sessions</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400">Review submitted, locked sessions and export reports.</p>
            </div>
            <button id="openHistory" class="btn-primary">View history</button>
          </div>
        </section>
      </div>
    `);

    wirePicker(focusClassroomId);
  };

  const classroomCards = () => {
    const list = filteredClassrooms();
    if (list.length === 0) {
      return `<div class="col-span-full rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">No classrooms match your filters.</div>`;
    }
    return list.map((c) => `
      <button data-classroom-id="${esc(c.id)}" class="card group p-5 text-left transition hover:border-blue-400 hover:shadow-md">
        <div class="flex items-start justify-between gap-3">
          <h4 class="text-base font-bold leading-snug group-hover:text-blue-600 dark:group-hover:text-blue-400">${esc(c.name)}</h4>
          <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">${esc(c.subject || 'General')}</span>
        </div>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">${esc(c.academicYear || '—')} &middot; ${esc(c.department || '—')} &middot; ${esc(c.section || '—')}</p>
        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><i data-lucide="users" class="h-3.5 w-3.5"></i>${c.studentsCount} students</span>
          <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><i data-lucide="clock" class="h-3.5 w-3.5"></i>${esc(c.startTime || '—')} – ${esc(c.endTime || '—')}</span>
        </div>
      </button>
    `).join('');
  };

  const wirePicker = (focusClassroomId) => {
    $('filterYear').addEventListener('change', (e) => {
      state.selection.year = e.target.value;
      resetDependents(1);
      renderPicker();
    });
    $('filterDepartment').addEventListener('change', (e) => {
      state.selection.department = e.target.value;
      resetDependents(2);
      renderPicker();
    });
    $('filterSection').addEventListener('change', (e) => {
      state.selection.section = e.target.value;
      resetDependents(3);
      renderPicker();
    });
    $('filterSubject').addEventListener('change', (e) => {
      state.selection.subject = e.target.value;
      renderPicker();
    });
    $('resetFilters').addEventListener('click', () => {
      state.selection = { year: '', department: '', section: '', subject: '' };
      renderPicker();
    });
    $('openHistory').addEventListener('click', openHistory);
    document.querySelectorAll('[data-classroom-id]').forEach((button) =>
      button.addEventListener('click', () => openClassroom(button.dataset.classroomId))
    );
  };

  // ---------------------------------------------------------------- classroom detail

  const openClassroom = async (classroomId) => {
    stopPolling();
    const classroom = state.allClassrooms.find((c) => c.id === classroomId);
    if (!classroom) return;
    state.classroom = classroom;

    render(`
      <div class="grid gap-5">
        <button id="backPicker" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
          <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to classrooms
        </button>

        <section class="card p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 class="text-xl font-extrabold tracking-tight">${esc(classroom.name)}</h3>
              <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <span>${esc(classroom.academicYear || '—')}</span><span>&middot;</span>
                <span>${esc(classroom.department || '—')}</span><span>&middot;</span>
                <span>${esc(classroom.section || '—')}</span><span>&middot;</span>
                <span>${esc(classroom.subject || 'General')}</span>
              </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
              <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">${classroom.studentsCount} students</span>
              <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">${esc(classroom.startTime || '—')} – ${esc(classroom.endTime || '—')}</span>
            </div>
          </div>
          <p class="mt-4 text-xs font-semibold uppercase tracking-[0.15em] text-blue-600 dark:text-blue-400">Step 1 &rarr; Classroom selected</p>
          <div class="mt-2 h-1.5 w-1/2 rounded-full bg-blue-100 dark:bg-blue-900"><div class="h-1.5 w-1/4 rounded-full bg-blue-500"></div></div>
        </section>

        <div class="grid gap-5 lg:grid-cols-5">
          <section class="card overflow-hidden lg:col-span-3">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
              <h4 class="text-base font-bold">Students</h4>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">${studentsPreview()}</div>
          </section>

          <div class="lg:col-span-2">
            <section class="card p-5">
              <h4 class="text-base font-bold">Assignments</h4>
              <div class="mt-3 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800"><i data-lucide="user" class="h-5 w-5"></i></div>
                <div>
                  <p class="text-sm font-semibold">${esc(classroom.teacher?.name || classroom.teacherId || '—')}</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">${esc(classroom.teacher?.id || '')}</p>
                </div>
              </div>
              <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-violet-50 px-3 py-1 font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"><i data-lucide="book-open" class="mr-1 inline h-3 w-3"></i>${esc(classroom.subject || 'General')}</span>
              </div>
            </section>

            <section class="card mt-5 p-5">
              <h4 class="text-base font-bold">Start an attendance session</h4>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sessions lock automatically when the scheduled end time passes and auto-submit is on.</p>
              <form id="startSessionForm" class="mt-4 grid gap-4">
                <label class="block">
                  <span class="mb-1.5 block text-sm font-semibold text-slate-600 dark:text-slate-300">Date</span>
                  <input type="date" id="sessionDate" value="${todayISO()}" required class="input w-full" />
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="block">
                    <span class="mb-1.5 block text-sm font-semibold text-slate-600 dark:text-slate-300">Start time</span>
                    <input type="time" id="sessionStart" value="${nowHM()}" class="input w-full" />
                  </label>
                  <label class="block">
                    <span class="mb-1.5 block text-sm font-semibold text-slate-600 dark:text-slate-300">Scheduled end</span>
                    <input type="time" id="sessionEnd" value="${esc(classroom.endTime || '')}" class="input w-full" />
                  </label>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                  <input type="checkbox" id="sessionAuto" checked class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                  Auto-submit when the session ends
                </label>
                <button type="submit" id="startSessionBtn" class="btn-primary w-full justify-center">Start session</button>
              </form>
            </section>
          </div>
        </div>
      </div>
    `);

    $('backPicker').addEventListener('click', () => renderPicker(classroom.id));
    $('startSessionForm').addEventListener('submit', startSession);
  };

  const studentsPreview = () => {
    const students = state.classroom?.students || [];
    if (students.length === 0) {
      return `<div class="px-5 py-10 text-center text-sm text-slate-400">No students enrolled yet.</div>`;
    }
    return students.map((s) => `
      <div class="flex items-center gap-3 px-5 py-3">
        <img src="${esc(photoUrl(s.photo))}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
        <div>
          <p class="text-sm font-semibold">${esc(s.firstName)} ${esc(s.lastName)}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">${esc(s.user_id || s.id || '')}</p>
        </div>
      </div>
    `).join('');
  };

  // ---------------------------------------------------------------- session lifecycle

  const startSession = async (event) => {
    event.preventDefault();
    const button = $('startSessionBtn');
    button.disabled = true;
    button.innerHTML = '<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>Starting&hellip;';
    if (window.lucide) lucide.createIcons();

    try {
      const payload = await API.request(`${API_BASE}/sessions`, {
        method: 'POST',
        body: JSON.stringify({
          classroomId: state.classroom.id,
          date: $('sessionDate').value,
          startTime: $('sessionStart').value || null,
          scheduledEndTime: $('sessionEnd').value || null,
          autoSubmit: $('sessionAuto').checked,
        }),
      });
      state.session = payload;
      renderMonitor();
    } catch (error) {
      showError(error.message);
    } finally {
      button.disabled = false;
      button.innerHTML = 'Start session';
    }
  };

  const stopPolling = () => {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  };

  const beginPolling = () => {
    stopPolling();
    pollTimer = setInterval(async () => {
      try {
        const payload = await API.request(`${API_BASE}/sessions/${state.session.session.id}`);
        if (payload.locked) {
          stopPolling();
          state.session = payload;
          renderLocked();
        } else if (JSON.stringify(payload.summary) !== JSON.stringify(state.session.summary)) {
          state.session = payload;
          renderMonitor();
        }
      } catch (error) {
        /* transient network failure — keep polling */
      }
    }, 20000);
  };

  const renderMonitor = () => {
    const payload = state.session;
    const { session, classroom, summary } = payload;

    render(`
      <div class="grid gap-5">
        <button id="monitorBack" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
          <i data-lucide="arrow-left" class="h-4 w-4"></i>End session and return to classrooms
        </button>

        <section class="card p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xl font-extrabold tracking-tight">Attendance session</h3>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">OPEN</span>
              </div>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">${esc(session.id)} &middot; ${esc(classroom.name)} &middot; ${esc(session.date)}</p>
            </div>
            <div class="text-right">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Time remaining</p>
              <p id="countdown" class="text-2xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">—</p>
            </div>
          </div>
          <p class="mt-4 text-xs font-semibold uppercase tracking-[0.15em] text-blue-600 dark:text-blue-400">Step 2 &rarr; Session running</p>
          <div class="mt-2 h-1.5 w-1/2 rounded-full bg-blue-100 dark:bg-blue-900"><div class="h-1.5 w-1/2 rounded-full bg-blue-500"></div></div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
          ${summaryChip('Total', summary.total, 'slate')}
          ${summaryChip('Present', summary.present, 'emerald')}
          ${summaryChip('Late', summary.late, 'amber')}
          ${summaryChip('Excused', summary.excused, 'violet')}
          ${summaryChip('Absent', summary.absent, 'rose')}
          ${summaryChip('Unmarked', summary.unmarked, 'slate')}
          ${summaryChip('Awaiting check', summary.pending, 'orange')}
        </section>

        <section class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h4 class="text-base font-bold">Student roster</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400">Conflicted students require adviser verification before marking.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                  <th class="px-5 py-3 font-semibold">Student</th>
                  <th class="px-3 py-3 font-semibold">Status</th>
                  <th class="px-3 py-3 font-semibold">Conflict</th>
                  <th class="px-3 py-3 font-semibold">Verification</th>
                  <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">${rosterRows()}</tbody>
            </table>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
            <button id="cancelSession" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900">Cancel session</button>
            <button id="submitSession" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700">Submit &amp; lock session</button>
          </div>
        </section>
      </div>
    `);

    wireMonitor();
  };

  const rosterRows = () => {
    const payload = state.session;
    const rows = payload.students.map((s) => {
      const pendingVerification = s.requiresVerification && s.verificationStatus === 'pending';
      const verified = s.verificationStatus === 'confirmed';
      const rejected = s.verificationStatus === 'rejected';
      const canVerify =
        (window.PORTAL_IS_ADMIN || (s.adviser ? s.adviser.id === ME : payload.classroom.teacherId === ME));

      const conflictCell = s.requiresVerification
        ? `<span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300" title="${quotes(s.conflicts.map((c) => `${c.name} (${c.subject})`).join(', '))}"><i data-lucide="alert-triangle" class="h-3 w-3"></i>${s.conflicts.length} overlapping class${s.conflicts.length === 1 ? '' : 'es'}</span>`
        : `<span class="text-xs text-slate-400">—</span>`;

      const verificationCell = pendingVerification
        ? `<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><i data-lucide="shield-alert" class="h-3 w-3"></i>Awaiting verification</span>`
        : verified
          ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><i data-lucide="shield-check" class="h-3 w-3"></i>Verified</span>`
          : rejected
            ? `<span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">Rejected</span>`
            : `<span class="text-xs text-slate-400">—</span>`;

      const markGroup = s.markable
        ? `<div class="flex justify-end gap-1.5">${['Present', 'Late', 'Absent', 'Excused'].map((status) => `
            <button data-mark="${esc(s.id)}" data-status="${status}" class="rounded-lg px-2.5 py-1.5 text-xs font-bold transition ${s.status === status ? statusBtnActive(status) : statusBtnIdle(status)}">${status}</button>`).join('')}</div>`
        : pendingVerification
          ? (canVerify
            ? `<div class="flex justify-end gap-1.5">
                <button data-verify="${esc(s.id)}" data-confirm="true" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">Verify</button>
                <button data-verify="${esc(s.id)}" data-confirm="false" class="rounded-lg bg-rose-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-rose-700">Reject</button>
              </div>`
            : `<span class="block text-right text-xs text-slate-400">Adviser must check</span>`)
          : `<span class="block text-right text-xs text-slate-400">Locked</span>`;

      return `
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
          <td class="px-5 py-3">
            <div class="flex items-center gap-3">
              <img src="${esc(photoUrl(s.photo))}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
              <div>
                <p class="font-semibold">${esc(s.firstName)} ${esc(s.lastName)}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">${esc(s.id)}</p>
              </div>
            </div>
          </td>
          <td class="px-3 py-3">${s.status ? statusChip(s.status) : `<span class="text-sm text-slate-400">Unmarked</span>`}</td>
          <td class="px-3 py-3">${conflictCell}</td>
          <td class="px-3 py-3">${verificationCell}</td>
          <td class="px-5 py-3">${markGroup}</td>
        </tr>`;
    });
    return rows.join('');
  };

  const statusBtnActive = (status) => {
    const map = { Present: 'bg-emerald-600 text-white', Late: 'bg-amber-500 text-white', Absent: 'bg-rose-600 text-white', Excused: 'bg-violet-600 text-white' };
    return map[status];
  };
  const statusBtnIdle = () =>
    'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700';

  const wireMonitor = () => {
    $('monitorBack').addEventListener('click', () => { stopPolling(); renderPicker(state.classroom.id); });
    $('submitSession').addEventListener('click', submitSession);
    $('cancelSession').addEventListener('click', cancelSession);
    document.querySelectorAll('[data-mark]').forEach((button) =>
      button.addEventListener('click', () => markStudent(button.dataset.mark, button.dataset.status, button))
    );
    document.querySelectorAll('[data-verify]').forEach((button) =>
      button.addEventListener('click', () => verifyStudent(button.dataset.verify, button.dataset.confirm === 'true', button))
    );
    startCountdown();
    beginPolling();
  };

  const remainingTime = () => {
    const end = state.session?.session?.scheduledEndTime;
    if (!end) return null;
    const [h, m] = end.split(':').map((n) => Number(n));
    const now = new Date();
    const endDate = new Date(now);
    endDate.setHours(h, m, 0, 0);
    return Math.max(0, endDate - now);
  };

  const startCountdown = () => {
    const el = $('countdown');
    if (!el) return;
    const tick = () => {
      const ms = remainingTime();
      if (ms === null) {
        el.textContent = 'No end time';
        return;
      }
      if (ms === 0) {
        el.textContent = 'Due';
        el.className = 'text-2xl font-extrabold tabular-nums text-amber-600 dark:text-amber-400';
        return;
      }
      const total = Math.ceil(ms / 1000);
      const mm = String(Math.floor(total / 60)).padStart(2, '0');
      const ss = String(total % 60).padStart(2, '0');
      el.textContent = `${mm}:${ss}`;
    };
    tick();
    setInterval(tick, 1000);
  };

  const markStudent = async (studentId, status, button) => {
    button.disabled = true;
    try {
      const payload = await API.request(`${API_BASE}/sessions/${state.session.session.id}/mark`, {
        method: 'POST',
        body: JSON.stringify({ studentId, status }),
      });
      state.session = payload;
      renderMonitor();
    } catch (error) {
      showError(error.message);
      button.disabled = false;
    }
  };

  const verifyStudent = async (studentId, confirmed, button) => {
    button.disabled = true;
    try {
      const payload = await API.request(`${API_BASE}/sessions/${state.session.session.id}/verify`, {
        method: 'POST',
        body: JSON.stringify({ studentId, confirmed }),
      });
      state.session = payload;
      renderMonitor();
    } catch (error) {
      showError(error.message);
      button.disabled = false;
    }
  };

  const submitSession = async () => {
    if (!window.confirm('Submit and lock this attendance session? Classroom marks will be locked and the session closed.')) return;
    const payload = await API.request(`${API_BASE}/sessions/${state.session.session.id}/submit`, { method: 'POST' });
    stopPolling();
    state.session = payload;
    renderLocked();
  };

  const cancelSession = async () => {
    if (!window.confirm('Cancel this attendance session? The session will be closed and no attendance recorded.')) return;
    try {
      const payload = await API.request(`${API_BASE}/sessions/${state.session.session.id}/cancel`, { method: 'POST' });
      stopPolling();
      state.session = payload;
      renderLocked();
    } catch (error) {
      showError(error.message);
    }
  };

  // ---------------------------------------------------------------- locked / history

  const renderLocked = () => {
    const payload = state.session;
    const { session, classroom, students, summary } = payload;
    const isCancelled = session.status === 'cancelled';

    render(`
      <div class="grid gap-5">
        <button id="lockBack" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400">
          <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to classrooms
        </button>

        <section class="card p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xl font-extrabold tracking-tight">Attendance session</h3>
                <span class="rounded-full ${isCancelled ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'} px-2.5 py-0.5 text-xs font-bold uppercase">${esc(session.status)}</span>
              </div>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">${esc(session.id)} &middot; ${esc(classroom.name)} &middot; ${esc(session.date)}</p>
            </div>
            <div class="text-right text-xs text-slate-500 dark:text-slate-400">
              ${session.lockedAt ? `<p>Locked ${esc(new Date(session.lockedAt).toLocaleString())}</p>` : ''}
              ${session.actualEndTime ? `<p class="mt-1">Ended at ${esc(session.actualEndTime)}</p>` : ''}
            </div>
          </div>
          ${isCancelled
            ? `<div class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">This session was cancelled — no classroom marks were recorded.</div>`
            : `<div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"><i data-lucide="lock" class="mr-1 inline h-4 w-4"></i>Session is locked. Marks are recorded — the assigned adviser reviews and submits them for admin finalization.</div>`}
          <p class="mt-4 text-xs font-semibold uppercase tracking-[0.15em] text-blue-600 dark:text-blue-400">Step 3 &rarr; Session completed</p>
          <div class="mt-2 h-1.5 w-1/2 rounded-full bg-blue-100 dark:bg-blue-900"><div class="h-1.5 w-full rounded-full bg-blue-500"></div></div>
        </section>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
          ${summaryChip('Total', summary.total, 'slate')}
          ${summaryChip('Present', summary.present, 'emerald')}
          ${summaryChip('Late', summary.late, 'amber')}
          ${summaryChip('Excused', summary.excused, 'violet')}
          ${summaryChip('Absent', summary.absent, 'rose')}
          ${summaryChip('Unmarked', summary.unmarked, 'slate')}
          ${summaryChip('Awaiting check', summary.pending, 'orange')}
        </section>

        <section class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <h4 class="text-base font-bold">Final roster</h4>
            ${isCancelled ? '' : `<a href="${API_BASE}/sessions/${esc(session.id)}/export" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"><i data-lucide="download" class="h-4 w-4"></i>Export CSV</a>`}
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
              <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                  <th class="px-5 py-3 font-semibold">Student</th>
                  <th class="px-3 py-3 font-semibold">Status</th>
                  <th class="px-3 py-3 font-semibold">Conflict</th>
                  <th class="px-3 py-3 font-semibold">Remarks</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                ${students.map((s) => `
                  <tr>
                    <td class="px-5 py-3">
                      <p class="font-semibold">${esc(s.firstName)} ${esc(s.lastName)}</p>
                      <p class="text-xs text-slate-500 dark:text-slate-400">${esc(s.id)}</p>
                    </td>
                    <td class="px-3 py-3">${s.status ? statusChip(s.status) : `<span class="text-sm text-slate-400">—</span>`}</td>
                    <td class="px-3 py-3">${s.requiresVerification ? `<span class="text-xs font-semibold text-rose-600 dark:text-rose-400">Conflict (${s.verificationStatus})</span>` : `<span class="text-xs text-slate-400">—</span>`}</td>
                    <td class="px-3 py-3"><span class="text-xs text-slate-500 dark:text-slate-400">${esc(s.remarks || '—')}</span></td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>
          <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
            <button id="lockOpenHistory" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900">History</button>
            <button id="lockBackBtn" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">Done</button>
          </div>
        </section>
      </div>
    `);

    $('lockBack').addEventListener('click', backToPicker);
    $('lockBackBtn').addEventListener('click', backToPicker);
    $('lockOpenHistory')?.addEventListener('click', openHistory);
  };

  const backToPicker = () => {
    stopPolling();
    renderPicker(state.classroom?.id || null);
  };

  const openHistory = async () => {
    let sessionsPayload;
    try {
      sessionsPayload = await API.request(API_BASE + '/sessions');
    } catch (error) {
      showError(error.message);
      return;
    }
    const sessions = sessionsPayload.sessions || [];

    renderModal(`
      <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm">
        <div class="mt-8 w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
          <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800">
            <div>
              <h3 class="text-lg font-bold">Past attendance sessions</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Submitted sessions are locked and read-only.</p>
            </div>
            <button id="closeHistory" class="rounded-lg bg-slate-100 p-2 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"><i data-lucide="x" class="h-4 w-4"></i></button>
          </div>
          <div class="mt-4 grid gap-3">
            ${sessions.length === 0 ? `<div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">No past sessions yet.</div>` : sessions.map((entry) => `
              <button data-history-session="${esc(entry.session.id)}" class="card flex w-full flex-wrap items-center justify-between gap-3 p-4 text-left hover:border-blue-400">
                <div>
                  <p class="text-sm font-bold">${esc(entry.classroom.name)}</p>
                  <p class="text-xs text-slate-500 dark:text-slate-400">${esc(entry.session.date)} &middot; ${esc(entry.session.id)}</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">${entry.studentsCount} students</span>
                  <span class="rounded-full ${entry.session.status === 'cancelled' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'} px-2.5 py-1 font-semibold uppercase">${esc(entry.session.status)}</span>
                  <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i>
                </div>
              </button>`).join('')}
          </div>
        </div>
      </div>
    `);

    $('closeHistory').addEventListener('click', () => $('modalRoot').innerHTML = '');
    $('modalRoot').addEventListener('click', (event) => {
      if (event.target === event.currentTarget.firstElementChild) $('modalRoot').innerHTML = '';
    });
    document.querySelectorAll('[data-history-session]').forEach((button) =>
      button.addEventListener('click', () => {
        $('modalRoot').innerHTML = '';
        viewHistorySession(button.dataset.historySession);
      })
    );
    if (window.lucide) lucide.createIcons();
  };

  const viewHistorySession = async (id) => {
    try {
      const payload = await API.request(`${API_BASE}/sessions/${id}`);
      state.session = payload;
      stopPolling();
      renderLocked();
    } catch (error) {
      showError(error.message);
    }
  };

  const renderModal = (html) => {
    $('modalRoot').innerHTML = html;
  };

  document.addEventListener('DOMContentLoaded', init);
})();