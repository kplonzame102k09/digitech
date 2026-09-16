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

  const API_BASE = '/admin/api/attendance/finalize';
  const ME = window.PORTAL_USER_ID;

  const app = () => $('app');
  const modalRoot = () => $('modalRoot');

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

  const avatarHtml = (name, id, photo, size = 'h-9 w-9') => `
    <span class="relative flex ${size} shrink-0 items-center justify-center rounded-full bg-violet-50 text-xs font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
      ${esc(initials(name, id))}
      <img src="${esc(photo ? DG.normalizePhotoUrl(photo) : '')}" alt="${esc(name)}" loading="lazy" class="absolute inset-0 ${size} rounded-full object-cover" onerror="this.onerror=null;this.src='/images/16432.png'" />
    </span>`;

  const statusPill = (status) => {
    if (status === 'finalized') {
      return `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i>Finalized</span>`;
    }
    if (status === 'returned') {
      return `<span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>Returned</span>`;
    }
    return `<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"><i data-lucide="hourglass" class="h-3.5 w-3.5"></i>Awaiting finalization</span>`;
  };

  const finalChip = (status) => {
    const map = { Present: 'emerald', Late: 'amber', Excused: 'violet', Absent: 'rose' };
    const color = map[status] || 'slate';
    return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold bg-${color}-100 text-${color}-700 dark:bg-${color}-500/10 dark:text-${color}-300">${esc(status || '—')}</span>`;
  };

  const checkChip = (check) => `
    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
      ${esc(check.classroomName || check.classroomId || 'Classroom')} &middot; ${esc(check.status || '—')}
    </span>`;

  const load = async () => {
    try {
      const data = await API.request(API_BASE);
      renderList(data.packages || []);
    } catch (error) {
      showError(error.message);
    }
  };

  const renderList = (packages) => {
    render(`
      <section class="card p-5 sm:p-6">
        <div class="mb-5 flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400">Submissions</p>
            <h3 class="mt-1 text-xl font-extrabold">Attendance packages</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Adviser-reviewed days ready to be finalized as the official record.</p>
          </div>
          <button type="button" id="refreshBtn" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 px-3.5 py-2 text-sm font-semibold hover:border-slate-300 dark:border-slate-700"><i data-lucide="refresh-ccw" class="h-4 w-4"></i>Refresh</button>
        </div>
        ${packages.length === 0
          ? `<div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">No attendance packages yet. Adviser submissions will appear here for finalization.</div>`
          : `<div class="overflow-x-auto"><table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-400 dark:border-slate-700">
                  <th class="px-3 py-2.5">Date</th>
                  <th class="px-3 py-2.5">Adviser</th>
                  <th class="px-3 py-2.5 text-center">Students</th>
                  <th class="px-3 py-2.5 text-center">Present</th>
                  <th class="px-3 py-2.5 text-center">Absent</th>
                  <th class="px-3 py-2.5">Status</th>
                  <th class="px-3 py-2.5">Submitted</th>
                  <th class="px-3 py-2.5 text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                ${packages.map((p) => `
                  <tr class="border-b border-slate-100 dark:border-slate-800">
                    <td class="px-3 py-3 font-semibold">${esc(p.date)}</td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2.5">
                        ${avatarHtml(p.adviser.name, p.adviser.id, p.adviser.photo)}
                        <div><b class="block">${esc(p.adviser.name)}</b><small class="text-xs text-slate-400">${esc(p.adviser.id)}</small></div>
                      </div>
                    </td>
                    <td class="px-3 py-3 text-center font-semibold">${p.summary.students}</td>
                    <td class="px-3 py-3 text-center text-emerald-600 dark:text-emerald-400">${p.summary.present}</td>
                    <td class="px-3 py-3 text-center text-rose-600 dark:text-rose-400">${p.summary.absent}</td>
                    <td class="px-3 py-3">${statusPill(p.status)}</td>
                    <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400">${p.submittedAt ? esc(new Date(p.submittedAt).toLocaleString()) : '—'}</td>
                    <td class="px-3 py-3 text-right">
                      <button type="button" data-open-package="${esc(p.id)}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600"><i data-lucide="eye" class="h-3.5 w-3.5"></i>Open</button>
                    </td>
                  </tr>`).join('')}
              </tbody>
            </table></div>`}
      </section>
    `);

    $('refreshBtn')?.addEventListener('click', load);
    document.querySelectorAll('[data-open-package]').forEach((button) =>
      button.addEventListener('click', () => openPackage(button.dataset.openPackage))
    );
  };

  const openPackage = async (id) => {
    try {
      const data = await API.request(`${API_BASE}/${encodeURIComponent(id)}`);
      renderDetail(data);
    } catch (error) {
      showError(error.message);
    }
  };

  const renderDetail = (data) => {
    const { package: pkg, students } = data;
    const finalized = pkg.status === 'finalized';
    const adviser = pkg.adviser || {};

    modalRoot().innerHTML = `
      <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Package detail">
        <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900">
          <div class="border-b border-slate-200 p-6 dark:border-slate-800">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400">Package ${esc(pkg.id)}</p>
                <h3 class="mt-1 text-2xl font-extrabold tracking-tight">${esc(pkg.date)}</h3>
                <div class="mt-2 flex items-center gap-2.5">
                  ${avatarHtml(adviser.name, adviser.id, adviser.photo)}
                  <span class="text-sm text-slate-500 dark:text-slate-400">Submitted by <b class="text-slate-700 dark:text-slate-200">${esc(adviser.name)}</b></span>
                </div>
              </div>
              <div class="text-right">${statusPill(pkg.status)}${pkg.finalizedAt ? `<p class="mt-2 text-xs text-slate-400">Finalized ${esc(new Date(pkg.finalizedAt).toLocaleString())}</p>` : ''}${pkg.returnedAt ? `<p class="mt-2 text-xs text-rose-500 dark:text-rose-400">Returned ${esc(new Date(pkg.returnedAt).toLocaleString())}${pkg.returnReason ? ` &middot; ${esc(pkg.returnReason)}` : ''}</p>` : ''}</div>
            </div>
          </div>
          <div class="p-6">
            <div class="mb-4 grid gap-3 sm:grid-cols-5">
              ${['Students', 'Present', 'Late', 'Excused', 'Absent'].map((label, i) => `<div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800"><div class="text-xl font-extrabold ${i >= 3 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">${[pkg.summary.students, pkg.summary.present, pkg.summary.late, pkg.summary.excused, pkg.summary.absent][i]}</div><div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">${label}</div></div>`).join('')}
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              ${students.map((s) => `
                <article class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                  <div class="flex flex-wrap items-center gap-3">
                    ${avatarHtml(s.name, s.id, s.photo)}
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-2"><b>${esc(s.name)}</b>${s.final ? finalChip(s.final.status) : finalChip(null)}</div>
                      <small class="text-xs text-slate-400">${esc(s.id)}${s.section ? ` &middot; ${esc(s.section)}` : ''}</small>
                      <div class="mt-1.5 flex flex-wrap gap-1">${s.checks.length ? s.checks.map(checkChip).join('') : '<span class="text-[11px] text-slate-400">No classroom marks</span>'}</div>
                    </div>
                  </div>
                </article>`).join('')}
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 p-5 dark:border-slate-800">
            <button type="button" id="closeDetail" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Close</button>
            ${finalized
              ? `<span class="inline-flex items-center gap-2 rounded-lg bg-emerald-100 px-4 py-2 text-sm font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><i data-lucide="shield-check" class="h-4 w-4"></i>Finalized</span>`
              : `<div class="flex flex-wrap items-center gap-2">${pkg.status === 'submitted' ? `<button type="button" id="returnBtn" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"><i data-lucide="rotate-ccw" class="h-4 w-4"></i>Return</button>` : ''}<button type="button" id="finalizeBtn" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500"><i data-lucide="shield-check" class="h-4 w-4"></i>Finalize package</button></div>`}
          </div>
        </div>
      </div>
    `;

    if (window.lucide) lucide.createIcons();

    const close = () => { modalRoot().innerHTML = ''; };
    $('closeDetail')?.addEventListener('click', close);
    modalRoot().addEventListener('click', (e) => { if (e.target === e.currentTarget) close(); });
    $('returnBtn')?.addEventListener('click', () => returnPackage(pkg, close));
    $('finalizeBtn')?.addEventListener('click', () => finalize(pkg.id, close));
  };

  const returnPackage = async (pkg, close) => {
    const reason = window.prompt('Return this package to the adviser with the reason it needs correction (shown to the adviser):');
    if (reason === null) return;
    if (!reason.trim()) {
      showError('A reason is required when returning a package.');
      return;
    }
    try {
      await API.request(`${API_BASE}/${encodeURIComponent(pkg.id)}/return`, {
        method: 'POST',
        body: JSON.stringify({ reason: reason.trim() }),
      });
      close();
      APP?.toast?.('Package returned to the adviser for correction.');
      load();
    } catch (error) {
      showError(error.message);
    }
  };

  const finalize = async (id, close) => {
    if (!window.confirm('Finalize this attendance package? Its official marks will be locked and will count in analytics, student, and parent views.')) return;
    try {
      await API.request(`${API_BASE}/${encodeURIComponent(id)}`, { method: 'POST' });
      close();
      APP?.toast?.('Attendance package finalized.');
      load();
    } catch (error) {
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