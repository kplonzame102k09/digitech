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

  const API_BASE = '/teacher/api/attendance/review';
  const ME = window.PORTAL_USER_ID;

  const todayISO = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
  };

  const state = {
    date: todayISO(),
    data: null,
    busy: false,
  };

  const app = () => $('app');

  const showError = (message) => {
    const el = $('appError');
    el.textContent = message;
    el.classList.remove('hidden');
  };
  const clearError = () => $('appError').classList.add('hidden');

  const render = (html) => {
    clearError();
    app().innerHTML = html;
    if (window.lucide) lucide.createIcons();
  };

  const initials = (name, id) => {
    const text = name === id ? id.slice(0, 2) : name.split(/\s+/).map((w) => w[0]).filter(Boolean).slice(0, 2).join('');
    return (text || id.slice(0, 2)).toUpperCase();
  };

  const avatarHtml = (student) => `
    <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-50 text-xs font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
      ${esc(initials(student.name, student.id))}
      <img src="${esc(student.photo ? DG.normalizePhotoUrl(student.photo) : '')}" alt="${esc(student.name)}" loading="lazy" class="absolute inset-0 h-10 w-10 rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
    </span>`;

  const statusChip = (status) => {
    const map = { Present: 'emerald', Late: 'amber', Excused: 'violet', Absent: 'rose' };
    const color = map[status] || 'slate';
    return `<span class="inline-flex items-center gap-1 rounded-full bg-${color}-100 px-2.5 py-1 text-xs font-bold ${color === 'slate' ? 'text-slate-600 dark:bg-slate-800 dark:text-slate-300' : `text-${color}-700 dark:bg-${color}-500/10 dark:text-${color}-300`}">${esc(status)}</span>`;
  };

  const checkChip = (check) => {
    const cls = check.status === 'Present'
      ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
      : check.status === 'Late'
        ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
        : check.status === 'Excused'
          ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
          : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
    return `
      <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ${cls}" title="Recorded by ${esc(check.teacherName || check.teacherId || '—')}">
        ${esc(check.classroomName || check.classroomId || 'Classroom')}${check.subject ? ` &middot; ${esc(check.subject)}` : ''} &middot; ${esc(check.status)}
      </span>`;
  };

  const statusButton = (student, status) => {
    const active = student.final?.status === status;
    const map = { Present: 'emerald', Late: 'amber', Excused: 'violet', Absent: 'rose' };
    const color = map[status];
    return `<button type="button" data-set-status data-student="${esc(student.id)}" data-status="${status}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-extrabold transition ${active ? `bg-${color}-600 text-white hover:bg-${color}-500` : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'}" ${state.busy ? 'disabled' : ''}>${esc(status)}</button>`;
  };

  const studentRow = (student) => {
    const marks = student.checks.length
      ? `<div class="mt-2 flex flex-wrap gap-1.5">${student.checks.map(checkChip).join('')}</div>`
      : `<p class="mt-2 text-xs text-slate-400">No classroom marks recorded yet for this day.</p>`;

    const control = student.editable
      ? `<div class="mt-3 flex flex-wrap gap-1.5">${['Present', 'Late', 'Absent', 'Excused'].map((s) => statusButton(student, s)).join('')}</div>`
      : student.final?.finalizedAt
        ? `<div class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"><i data-lucide="shield-check" class="h-4 w-4"></i>Finalized ${student.final.status}</div>`
        : `<div class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400"><i data-lucide="send" class="h-4 w-4"></i>Submitted to admin ${student.final ? `&middot; ${student.final.status}` : ''}</div>`;

    return `
      <article class="card p-4" data-review-row>
        <div class="flex flex-wrap items-center gap-3">
          ${avatarHtml(student)}
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <b class="truncate">${esc(student.name)}</b>
              ${student.final ? statusChip(student.final.status) : ''}
            </div>
            <small class="text-xs text-slate-400">${esc(student.id)}${student.section ? ` &middot; ${esc(student.section)}` : ''}</small>
            ${marks}
            ${control}
          </div>
        </div>
      </article>`;
  };

  const packageBanner = (pkg) => {
    if (!pkg) return null;
    if (pkg.status === 'finalized') {
      return `<div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300"><i data-lucide="shield-check" class="mr-1 inline h-4 w-4"></i>Finalized on ${esc(pkg.finalizedAt ? new Date(pkg.finalizedAt).toLocaleString() : '')}${pkg.finalizedBy ? ` by ${esc(pkg.finalizedBy)}` : ''}.</div>`;
    }
    if (pkg.status === 'returned') {
      const reason = esc(pkg.returnReason || 'No reason given.');
      return `<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"><i data-lucide="rotate-ccw" class="mr-1 inline h-4 w-4"></i>Returned by the admin on ${esc(pkg.returnedAt ? new Date(pkg.returnedAt).toLocaleString() : '')} — <b>${reason}</b> Review the marks below, correct what's needed, then resubmit the day.</div>`;
    }
    return `<div class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"><i data-lucide="send" class="mr-1 inline h-4 w-4"></i>Submitted to the admin on ${esc(pkg.submittedAt ? new Date(pkg.submittedAt).toLocaleString() : '')}. Awaiting finalization.</div>`;
  };

  const counts = (students) => {
    const finals = students.filter((s) => s.final).map((s) => s.final.status);
    return {
      total: students.length,
      decided: finals.length,
      present: finals.filter((s) => ['Present', 'Late', 'Excused'].includes(s)).length,
      absent: finals.filter((s) => s === 'Absent').length,
      pending: students.length - finals.length,
    };
  };

  const renderReview = () => {
    const { date, teacher, package: pkg, students } = state.data;
    const c = counts(students);
    const editable = students.some((s) => s.editable);

    render(`
      <div class="grid gap-5">
        <section class="card p-6">
          <div class="flex flex-wrap items-center gap-4">
            <label class="block">
              <span class="mb-1.5 flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300"><i data-lucide="calendar" class="h-4 w-4 text-blue-500"></i>Attendance date</span>
              <input id="reviewDate" type="date" value="${esc(date)}" class="input rounded border px-3 py-2" />
            </label>
            <div class="flex flex-wrap gap-1.5 text-xs">
              <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><b>${c.total}</b> advisees</span>
              <span class="rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300"><b>${c.present}</b> present</span>
              <span class="rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-600 dark:bg-rose-950/40 dark:text-rose-300"><b>${c.absent}</b> absent</span>
              <span class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-600 dark:bg-amber-950/40 dark:text-amber-300"><b>${c.pending}</b> pending</span>
            </div>
          </div>
          ${packageBanner(pkg)}
        </section>

        ${students.length === 0
          ? `<section class="card p-10 text-center"><div class="teacher-empty-state"><i data-lucide="users-round"></i><b>No assigned learners</b><p>Learners appear here once an admin assigns you as their adviser. Ask the registrar to open <b>Admin &rarr; Enrollment</b> and pick you in the <b>Adviser</b> column of each student record.</p></div></section>`
          : `<div class="grid gap-3 md:grid-cols-2">${students.map(studentRow).join('')}</div>`}

        ${editable && students.length > 0
          ? `<section class="card sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 border-2 border-blue-200 p-4 dark:border-blue-900">
              <div class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <i data-lucide="send" class="h-5 w-5 text-blue-500"></i>
                ${c.pending > 0 ? `Review ${c.pending} learner${c.pending === 1 ? '' : 's'} still pending a final status.` : 'Every learner has a final status.'}
              </div>
              <button type="button" id="submitBtn" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-500" ${state.busy ? 'disabled' : ''}>
                <i data-lucide="shield-check" class="h-4 w-4"></i>Submit to admin
              </button>
            </section>`
          : ''}
      </div>
    `);

    $('reviewDate')?.addEventListener('change', () => {
      state.date = $('reviewDate').value || todayISO();
      $('submitBtn')?.setAttribute('disabled', 'disabled');
      load();
    });

    document.querySelectorAll('[data-set-status]').forEach((button) =>
      button.addEventListener('click', () => setStatus(button.dataset.student, button.dataset.status))
    );

    $('submitBtn')?.addEventListener('click', submit);
  };

  const load = async () => {
    try {
      const data = await API.request(`${API_BASE}?date=${encodeURIComponent(state.date)}`);
      state.data = data;
      renderReview();
    } catch (error) {
      showError(error.message);
    }
  };

  const setStatus = async (studentId, status) => {
    state.busy = true;
    $('submitBtn')?.setAttribute('disabled', 'disabled');
    try {
      const data = await API.request(API_BASE, {
        method: 'POST',
        body: JSON.stringify({ date: state.date, studentId, status }),
      });
      state.data.students = data.students;
      state.busy = false;
      renderReview();
      APP?.toast?.(`${status} saved for ${studentId}`);
    } catch (error) {
      state.busy = false;
      renderReview();
      showError(error.message);
    }
  };

  const submit = async () => {
    if (state.busy) return;
    if (!window.confirm('Submit this day to the admin for finalization? You will no longer be able to edit these marks.')) return;
    state.busy = true;
    $('submitBtn').setAttribute('disabled', 'disabled');
    try {
      state.data = await API.request(`${API_BASE}/submit`, {
        method: 'POST',
        body: JSON.stringify({ date: state.date }),
      });
      state.busy = false;
      renderReview();
      APP?.toast?.('Submitted to the admin for finalization.');
    } catch (error) {
      state.busy = false;
      renderReview();
      showError(error.message);
    }
  };

  const init = async () => {
    APP?.applyTheme?.();
    document.querySelectorAll('[data-theme-toggle]').forEach((button) =>
      button.addEventListener('click', () => APP?.toggleTheme?.())
    );
    await load();
  };

  init();
})();