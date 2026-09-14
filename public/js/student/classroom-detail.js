(() => {
  const U = AUTH.requireRole("student");
  if (!U) return;

  const classroomId = document.body.dataset.classroomId;
  if (!classroomId) return;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => APP.esc(value ?? "");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";
  const fallbackAvatar = (window.DG && DG.DEFAULT_AVATAR) || "/images/16432.png";

  let activities = [];

  const setError = (message) => {
    const box = $("detailError");
    if (!box) return;
    box.textContent = message || "";
    box.classList.toggle("hidden", !message);
  };

  async function api(url, options = {}) {
    const response = await fetch(url, {
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Request failed (${response.status})`);
    }
    return data;
  }

  const photoOf = (p) => {
    if (window.DG && typeof DG.normalizePhotoUrl === "function") return DG.normalizePhotoUrl(p);
    if (!p) return fallbackAvatar;
    if (/^(?:https?:|data:)/.test(p)) return p;
    if (p.startsWith("/")) return p;
    return `/storage/${p.replace(/^\/+/, "")}`;
  };

  function wireTabs() {
    document.querySelectorAll("[data-detail-tab]").forEach((button) => {
      button.addEventListener("click", () => {
        const target = button.dataset.detailTab;
        document.querySelectorAll("[data-detail-tab]").forEach((tab) => {
          const active = tab.dataset.detailTab === target;
          tab.classList.toggle("font-semibold", active);
          tab.classList.toggle("text-slate-900", active);
          tab.classList.toggle("dark:text-white", active);
          tab.classList.toggle("text-slate-500", !active);
          tab.classList.toggle("dark:text-slate-400", !active);
          tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        document.querySelectorAll("[data-detail-panel]").forEach((panel) => {
          panel.classList.toggle("hidden", panel.dataset.detailPanel !== target);
        });
      });
    });
  }

  async function loadDetail() {
    setError("");
    try {
      const data = await api(`/student/api/classrooms/${encodeURIComponent(classroomId)}`);
      const c = data.classroom;
      $("detailName").textContent = c.name || "Classroom";
      $("detailMeta").textContent = `${c.subject || ""} · ${c.teacherName || "Your teacher"} · ${c.studentsCount ?? ""} students`;
      $("detailDesc").textContent = c.description || "";
      renderClassmates(data.classmates || []);
    } catch (error) {
      setError(error.message || "Failed to load classroom.");
    }
  }

  function renderClassmates(classmates) {
    const grid = $("classmateGrid");
    if (!classmates.length) {
      grid.innerHTML = `<div class="card p-8 text-center text-sm text-slate-500 md:col-span-2 dark:text-slate-400">No classmates yet.</div>`;
      return;
    }
    grid.innerHTML = classmates.map((s) => {
      const name = `${s.firstName || ""} ${s.lastName || ""}`.trim() || s.id;
      const isMe = s.id === U.user_id || s.id === U.id;
      return `<div class="card flex items-center gap-3 p-4">
        <img src="${esc(photoOf(s.photo))}" alt="${esc(name)}" loading="lazy" class="h-11 w-11 rounded-full object-cover" onerror="this.onerror=null;this.src='${fallbackAvatar}'" />
        <div class="min-w-0 flex-1">
          <b class="block truncate">${esc(name)}${isMe ? " (you)" : ""}</b>
          <p class="truncate text-xs text-slate-400">${esc(s.email || s.id)}</p>
        </div>
      </div>`;
    }).join("");
    lucide.createIcons();
  }

  async function loadActivities() {
    setError("");
    try {
      const data = await api(`/student/api/classrooms/${encodeURIComponent(classroomId)}/activities`);
      activities = data.activities || [];
      renderActivities();
      renderScores();
    } catch (error) {
      setError(error.message || "Failed to load activities.");
    }
  }

  function renderActivities() {
    const wrap = $("activityGroups");
    const groups = ["Prelim", "Midterm", "Finals"].map((term) => ({
      term,
      items: activities.filter((a) => (a.term || "Prelim") === term),
    })).filter((g) => g.items.length);
    if (!groups.length) {
      wrap.innerHTML = `<div class="card p-8 text-center text-sm text-slate-500 dark:text-slate-400">No activities yet.</div>`;
      return;
    }
    wrap.innerHTML = groups.map((g) => `
      <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">${g.term}</p>
        <div class="mt-3 space-y-3">${g.items.map((a) => {
          const sub = a.submission;
          return `<div class="rounded bg-slate-50 p-4 dark:bg-slate-800">
            <b class="block">${esc(a.title)}</b>
            ${a.description ? `<p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">${esc(a.description)}</p>` : ""}
            <p class="mt-1 text-[11px] text-slate-400">${a.dueDate ? `Due ${esc(a.dueDate)} · ` : ""}${sub ? `${esc(sub.status)}${sub.score !== null && sub.score !== undefined ? ` · score ${esc(sub.score)}` : ""}` : "Not submitted"}</p>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
              <input type="file" data-file-for="${esc(a.id)}" accept=".pdf,image/jpeg,image/png,image/webp" class="text-xs" />
              <input type="text" data-notes-for="${esc(a.id)}" maxlength="2000" placeholder="Notes (optional)" class="input flex-1 rounded border px-2 py-1.5 text-xs" />
              <button type="button" data-submit-activity="${esc(a.id)}" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">${sub ? "Re-submit" : "Submit"}</button>
            </div>
          </div>`;
        }).join("")}</div>
      </div>`).join("");
    wrap.querySelectorAll("[data-submit-activity]").forEach((btn) =>
      btn.addEventListener("click", () => submitActivity(btn.dataset.submitActivity)),
    );
    lucide.createIcons();
  }

  function renderScores() {
    const body = $("scoreRows");
    const scored = activities.filter((a) => a.submission);
    if (!scored.length) {
      body.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-slate-500 dark:text-slate-400">No submissions yet — your scores will appear here.</td></tr>`;
      return;
    }
    body.innerHTML = scored.map((a) => {
      const sub = a.submission;
      return `<tr class="border-t border-slate-100 dark:border-slate-800">
        <td class="p-4 font-semibold">${esc(a.title)}</td>
        <td class="p-4"><span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">${esc(a.term || "Prelim")}</span></td>
        <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${sub.status === "Graded" ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" : sub.status === "Returned" ? "bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300" : "bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300"}">${esc(sub.status)}</span></td>
        <td class="p-4 font-bold">${sub.score !== null && sub.score !== undefined ? esc(sub.score) : "—"}</td>
        <td class="p-4 text-xs text-slate-500 dark:text-slate-400">${esc(sub.submittedAt ? String(sub.submittedAt).slice(0, 10) : "—")}</td>
      </tr>`;
    }).join("");
  }

  async function uploadFile(file) {
    const form = new FormData();
    form.append("file", file);
    form.append("classroom_id", classroomId);
    const response = await fetch("/api/classroom-files", {
      method: "POST",
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
      body: form,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Upload failed (${response.status})`);
    }
    return data.fileUrl;
  }

  async function submitActivity(activityId) {
    setError("");
    const fileInput = document.querySelector(`[data-file-for="${CSS.escape(activityId)}"]`);
    const notesInput = document.querySelector(`[data-notes-for="${CSS.escape(activityId)}"]`);
    const file = fileInput?.files?.[0];
    if (!file) {
      setError("Choose a file to submit (PDF or image, up to 5 MB).");
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setError("File must be 5 MB or smaller.");
      return;
    }
    try {
      const fileUrl = await uploadFile(file);
      await api(`/student/api/activities/${encodeURIComponent(activityId)}/submit`, {
        method: "POST",
        body: JSON.stringify({ fileUrl, notes: notesInput?.value?.trim() || null }),
      });
      APP.toast("Submitted!");
      loadActivities();
    } catch (error) {
      setError(error.message || "Submit failed.");
    }
  }

  // Meet-style video (self-hosted LiveKit; degrades gracefully if unconfigured).
  try {
    const section = document.querySelector("[data-video-section]");
    if (section && window.ClassroomVideo) {
      window.ClassroomVideo.init({
        classroomId,
        meetingsUrl: `/student/api/classrooms/${encodeURIComponent(classroomId)}/meetings`,
        tokenUrl: `/student/api/classrooms/${encodeURIComponent(classroomId)}/video/token`,
        isTeacher: false,
        listEl: document.getElementById("videoMeetings-student"),
        errorEl: document.getElementById("videoError-student"),
        historyEl: document.getElementById("videoHistory-student"),
        overlayEl: section.querySelector("[data-video-overlay]"),
        gridEl: section.querySelector("[data-video-grid]"),
        statusEl: section.querySelector("[data-video-status]"),
        joinInstantBtn: section.querySelector("[data-video-join-instant]"),
      });
    }
  } catch (_) { /* video optional; page works without it */ }

  wireTabs();
  loadDetail();
  loadActivities();
  // Live updates: refresh detail/activities/scores when idle and not in a
  // call (loadActivities also refreshes scores).
  document.addEventListener("digitech:tick", () => {
    if (!window.DG_SYNC?.idle()) return;
    const section = document.querySelector("[data-video-section]");
    if (section && !section.querySelector("[data-video-overlay]")?.classList.contains("hidden")) return;
    loadDetail();
    loadActivities();
  });
})();
