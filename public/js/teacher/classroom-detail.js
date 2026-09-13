(() => {
  const U = AUTH.requireRole("teacher");
  if (!U) return;

  const classroomId = document.body.dataset.classroomId;
  if (!classroomId) return;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => APP.esc(value ?? "");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";
  const fallbackAvatar = (window.DG && DG.DEFAULT_AVATAR) || "/images/16432.png";

  let classroom = null;
  let activities = [];
  let submissions = [];
  let activeSubmission = null;

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
    if (url.endsWith("/export") && response.ok) return response;
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

  const fileNameOf = (url) => {
    if (!url) return "No file";
    try {
      return decodeURIComponent(String(url).split("/").pop().split("?")[0] || url);
    } catch (_) {
      return url;
    }
  };

  const gwaOf = (average) => {
    if (average === null || average === undefined || average === "") return "—";
    if (window.FEATURES && typeof FEATURES.gwa === "function") {
      const g = FEATURES.gwa(Number(average));
      return window.FEATURES.gwaText ? FEATURES.gwaText(g) : g;
    }
    const v = Number(average);
    if (v >= 97) return "1.00";
    if (v >= 94) return "1.25";
    if (v >= 91) return "1.50";
    if (v >= 88) return "1.75";
    if (v >= 85) return "2.00";
    if (v >= 82) return "2.25";
    if (v >= 79) return "2.50";
    if (v >= 76) return "2.75";
    if (v >= 75) return "3.00";
    return "5.00";
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
          tab.setAttribute("aria-selected", active ? "true" : "false");
        });
        document.querySelectorAll("[data-detail-panel]").forEach((panel) => {
          panel.classList.toggle("hidden", panel.dataset.detailPanel !== target);
        });
        if (target === "submissions") loadSubmissions();
        if (target === "grades") loadGradebook();
        if (target === "activities") loadActivities();
      });
    });
  }

  async function loadClassroom() {
    setError("");
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}`);
      classroom = data.classroom;
      $("detailName").textContent = classroom.name || "";
      $("detailMeta").textContent = `${classroom.subject || ""} · ${classroom.status}`;
      $("detailStatus").textContent = classroom.status;
      $("detailCode").textContent = classroom.inviteCodeFormatted || classroom.inviteCode || "";
      $("detailLink").value = classroom.joinLink || "";
      $("detailSubjectInline").textContent = classroom.subject || "";
      $("activitySubject").textContent = classroom.subject || "";
      $("gradebookSubject").textContent = classroom.subject || "";
      $("archiveToggle").textContent = classroom.status === "active" ? "Archive" : "Reopen";
      renderRoster(classroom.students || []);
    } catch (error) {
      setError(error.message || "Failed to load classroom.");
    }
  }

  function renderRoster(students) {
    const body = $("rosterRows");
    if (!students.length) {
      body.innerHTML = `<tr><td colspan="4" class="p-6 text-center text-slate-500">No students yet — share the code or link.</td></tr>`;
      return;
    }
    body.innerHTML = students.map((s) => {
      const name = `${s.firstName || ""} ${s.lastName || ""}`.trim() || s.id;
      return `<tr class="border-t border-slate-100 dark:border-slate-800">
        <td class="p-4"><div class="flex items-center gap-3">
          <img src="${esc(photoOf(s.photo))}" alt="${esc(name)}" loading="lazy" class="h-9 w-9 rounded-full object-cover" onerror="this.onerror=null;this.src='${fallbackAvatar}'" />
          <b>${esc(name)}</b>
        </div></td>
        <td class="p-4 font-mono text-xs text-slate-500">${esc(s.id)}</td>
        <td class="p-4 text-xs text-slate-500">${esc(s.joinedAt ? String(s.joinedAt).slice(0, 10) : "—")}</td>
        <td class="p-4 text-right"><button type="button" data-remove="${esc(s.id)}" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove</button></td>
      </tr>`;
    }).join("");
    body.querySelectorAll("[data-remove]").forEach((btn) =>
      btn.addEventListener("click", async () => {
        if (!confirm("Remove this student from the classroom?")) return;
        try {
          await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/students/${encodeURIComponent(btn.dataset.remove)}`, { method: "DELETE" });
          loadClassroom();
        } catch (error) {
          setError(error.message || "Failed to remove student.");
        }
      }),
    );
    lucide.createIcons();
  }

  async function loadActivities() {
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/activities`);
      activities = data.activities || [];
      renderActivities();
    } catch (error) {
      setError(error.message || "Failed to load activities.");
    }
  }

  function renderActivities() {
    const termFilter = $("activityTermFilter")?.value || "all";
    const list = $("activityList");
    const shown = activities.filter((a) => termFilter === "all" || (a.term || "Prelim") === termFilter);
    list.innerHTML = shown.length
      ? shown.map((a) => `
        <div class="card p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <b>${esc(a.title)}</b>
                <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">${esc(a.term || "Prelim")}</span>
              </div>
              <p class="mt-0.5 text-xs text-slate-400">${a.dueDate ? `Due ${esc(a.dueDate)}` : "No due date"} · ${Number(a.submissionsCount || 0)} submissions</p>
            </div>
            <button type="button" data-del-activity="${esc(a.id)}" class="rounded-lg p-2 text-rose-500 hover:bg-rose-50" title="Delete activity"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
          </div>
          ${a.description ? `<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">${esc(a.description)}</p>` : ""}
        </div>`).join("")
      : `<div class="card p-8 text-center text-sm text-slate-500 md:col-span-2">No activities yet. Add the first one above.</div>`;
    list.querySelectorAll("[data-del-activity]").forEach((btn) =>
      btn.addEventListener("click", async () => {
        if (!confirm("Delete this activity and all its submissions?")) return;
        try {
          await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/activities/${encodeURIComponent(btn.dataset.delActivity)}`, { method: "DELETE" });
          loadActivities();
        } catch (error) {
          setError(error.message || "Failed to delete activity.");
        }
      }),
    );
    lucide.createIcons();
  }

  async function loadSubmissions() {
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/submissions`);
      submissions = data.submissions || [];
      renderSubmissions();
    } catch (error) {
      setError(error.message || "Failed to load submissions.");
    }
  }

  function renderSubmissions() {
    const filter = $("submissionFilter")?.value || "all";
    const body = $("submissionRows");
    const rows = submissions.filter((s) => filter === "all" || s.status === filter);
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-500">No submissions yet.</td></tr>`;
      return;
    }
    body.innerHTML = rows.map((s) => `
      <tr class="border-t border-slate-100 dark:border-slate-800">
        <td class="p-4"><div class="flex items-center gap-3">
          <img src="${esc(photoOf(s.studentPhoto))}" alt="${esc(s.studentName)}" loading="lazy" class="h-9 w-9 rounded-full object-cover" onerror="this.onerror=null;this.src='${fallbackAvatar}'" />
          <div><b class="block">${esc(s.studentName)}</b><small class="font-mono text-[11px] text-slate-400">${esc(s.studentId)}</small></div>
        </div></td>
        <td class="p-4"><div>${esc(s.activityTitle || "—")}</div><span class="mt-0.5 inline-block rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">${esc(s.activityTerm || "Prelim")}</span></td>
        <td class="p-4 text-xs text-slate-500">${esc(fileNameOf(s.fileUrl))}</td>
        <td class="p-4 text-xs text-slate-500">${esc(s.submittedAt ? String(s.submittedAt).slice(0, 10) : "—")}</td>
        <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${s.status === "Graded" ? "bg-emerald-50 text-emerald-700" : s.status === "Returned" ? "bg-amber-50 text-amber-700" : "bg-blue-50 text-blue-700"}">${esc(s.status)}</span></td>
        <td class="p-4 font-bold">${s.score !== null && s.score !== undefined ? esc(s.score) : "—"}</td>
        <td class="p-4 text-right"><button type="button" data-view-sub="${esc(s.id)}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600 dark:bg-slate-100 dark:text-slate-900"><i data-lucide="eye" class="h-3.5 w-3.5"></i>View</button></td>
      </tr>`).join("");
    body.querySelectorAll("[data-view-sub]").forEach((btn) =>
      btn.addEventListener("click", () => openSubmission(btn.dataset.viewSub)),
    );
    lucide.createIcons();
  }

  async function openSubmission(id) {
    setError("");
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/submissions/${encodeURIComponent(id)}`);
      const s = data.submission;
      activeSubmission = s;
      $("subPhoto").src = photoOf(s.studentPhoto);
      $("subStudent").textContent = s.studentName || s.studentId;
      $("subMeta").textContent = `${s.studentId} · ${s.studentEmail || ""} · submitted ${s.submittedAt ? String(s.submittedAt).slice(0, 10) : "—"}`;
      $("subSubject").textContent = data.submission.subject || classroom?.subject || "";
      $("subActivity").textContent = `${s.activityTitle || ""} · ${s.activityTerm || "Prelim"}`;
      $("subNotes").textContent = s.notes || "No notes.";
      $("subStatus").textContent = `${s.status}${s.score !== null && s.score !== undefined ? ` · ${s.score}` : ""}`;
      $("subScore").value = s.score ?? "";
      const preview = $("subPreview");
      const url = s.fileUrl || "";
      if (/\.(png|jpe?g|webp|gif)(\?|$)/i.test(url)) {
        preview.innerHTML = `<img src="${esc(url)}" alt="Submission" class="max-h-96 w-full object-contain bg-slate-100 dark:bg-slate-800" onerror="this.outerHTML='<p class=&quot;p-6 text-center text-sm text-slate-500&quot;>Preview unavailable — use Download.</p>'" />`;
      } else if (/\.pdf(\?|$)/i.test(url)) {
        preview.innerHTML = `<iframe src="${esc(url)}" class="h-96 w-full" title="Submission preview"></iframe>`;
      } else if (url) {
        preview.innerHTML = `<p class="p-6 text-center text-sm text-slate-500">Preview unavailable for this file type — use Download.</p>`;
      } else {
        preview.innerHTML = `<p class="p-6 text-center text-sm text-slate-500">No file attached.</p>`;
      }
      const dl = $("subDownload");
      dl.href = url || "#";
      dl.download = "";
      $("subOpen").href = url || "#";
      $("subGradeInfo").textContent = "Per-term grades live in the Grades tab.";
      $("submissionDialog")?.showModal();
      lucide.createIcons();
    } catch (error) {
      setError(error.message || "Failed to open submission.");
    }
  }

  async function loadGradebook() {
    try {
      const params = new URLSearchParams({
        semester: $("gradeSemester")?.value || "1st Semester",
        schoolYear: $("gradeYear")?.value || "",
      }).toString();
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/gradebook?${params}`);
      const body = $("gradebookRows");
      if (!data.rows.length) {
        body.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-500">No roster students.</td></tr>`;
        return;
      }
      const avgCell = (v, n) => v !== null && v !== undefined
        ? `<b>${esc(v)}</b><br /><small class="text-[11px] text-slate-400">n=${n ?? 0}</small>`
        : "—";
      body.innerHTML = data.rows.map((r) => {
        const name = `${r.firstName || ""} ${r.lastName || ""}`.trim() || r.studentId;
        return `<tr class="border-t border-slate-100 dark:border-slate-800" data-grade-row="${esc(r.studentId)}">
          <td class="p-3"><b>${esc(name)}</b><br /><small class="font-mono text-[11px] text-slate-400">${esc(r.studentId)}</small></td>
          <td class="p-3 text-sm text-violet-700 dark:text-violet-300">${avgCell(r.prelimAvg, r.termCounts?.Prelim)}</td>
          <td class="p-3 text-sm text-violet-700 dark:text-violet-300">${avgCell(r.midtermAvg, r.termCounts?.Midterm)}</td>
          <td class="p-3 text-sm text-violet-700 dark:text-violet-300">${avgCell(r.finalsAvg, r.termCounts?.Finals)}</td>
          <td class="p-3"><input data-g="prelim" type="number" min="0" max="100" step="0.01" value="${r.grade?.prelim ?? ""}" class="input w-24 rounded-lg border px-2 py-1.5" /></td>
          <td class="p-3"><input data-g="midterm" type="number" min="0" max="100" step="0.01" value="${r.grade?.midterm ?? ""}" class="input w-24 rounded-lg border px-2 py-1.5" /></td>
          <td class="p-3"><input data-g="finals" type="number" min="0" max="100" step="0.01" value="${r.grade?.finals ?? ""}" class="input w-24 rounded-lg border px-2 py-1.5" /></td>
          <td class="p-3 font-bold" data-g-final>${r.grade?.finalGrade ?? r.computedFinal ?? "—"}</td>
          <td class="p-3 font-bold text-blue-600" data-g-gwa>${r.grade && r.grade.finalGrade !== null && r.grade.finalGrade !== undefined ? gwaOf(r.grade.finalGrade) : (r.computedGwa ?? "—")}</td>
          <td class="p-3 text-right"><div class="flex justify-end gap-1.5">
            <button type="button" data-apply-grade="${esc(r.studentId)}" title="Fill prelim/midterm/finals from activity averages" class="rounded-xl border px-3 py-2 text-xs font-semibold">Apply avg</button>
            <button type="button" data-save-grade="${esc(r.studentId)}" class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
          </div></td>
        </tr>`;
      }).join("");
      body.querySelectorAll("[data-save-grade]").forEach((btn) =>
        btn.addEventListener("click", () => saveGradeRow(btn.dataset.saveGrade)),
      );
      body.querySelectorAll("[data-apply-grade]").forEach((btn) =>
        btn.addEventListener("click", () => applyAverages(btn.dataset.applyGrade)),
      );
    } catch (error) {
      setError(error.message || "Failed to load gradebook.");
    }
  }

  async function applyAverages(studentId) {
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/gradebook/${encodeURIComponent(studentId)}`, {
        method: "PUT",
        body: JSON.stringify({
          autofill: true,
          semester: $("gradeSemester")?.value || "1st Semester",
          schoolYear: $("gradeYear")?.value || undefined,
        }),
      });
      APP.toast(`Applied averages — final ${data.grade?.finalGrade ?? "—"}, GWA ${data.grade?.gwa ?? "—"}`);
      loadGradebook();
    } catch (error) {
      setError(error.message || "Failed to apply averages.");
    }
  }

  async function saveGradeRow(studentId, prelim, midterm, finals) {
    const row = document.querySelector(`[data-grade-row="${CSS.escape(studentId)}"]`);
    const val = (key) => {
      if (key === "prelim" && prelim !== undefined) return prelim;
      if (key === "midterm" && midterm !== undefined) return midterm;
      if (key === "finals" && finals !== undefined) return finals;
      const input = row?.querySelector(`[data-g="${key}"]`);
      const v = input?.value;
      return v === "" || v === undefined ? null : Number(v);
    };
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/gradebook/${encodeURIComponent(studentId)}`, {
        method: "PUT",
        body: JSON.stringify({
          prelim: val("prelim"),
          midterm: val("midterm"),
          finals: val("finals"),
          semester: $("gradeSemester")?.value || "1st Semester",
          schoolYear: $("gradeYear")?.value || undefined,
        }),
      });
      const finalCell = row?.querySelector("[data-g-final]");
      if (finalCell) finalCell.textContent = data.grade?.finalGrade ?? "—";
      const gwaCell = row?.querySelector("[data-g-gwa]");
      if (gwaCell) gwaCell.textContent = data.grade?.gwa ?? "—";
      APP.toast(`Saved — final ${data.grade?.finalGrade ?? "—"}, GWA ${data.grade?.gwa ?? "—"} (${data.grade?.remarks ?? ""})`);
    } catch (error) {
      setError(error.message || "Failed to save grade.");
    }
  }

  // Header actions
  const copyText = async (value) => {
    try {
      await navigator.clipboard.writeText(value);
      APP.toast("Copied to clipboard");
    } catch (_) {
      APP.toast("Copy failed — select and copy manually", "error");
    }
  };
  $("copyCode")?.addEventListener("click", () => copyText($("detailCode").textContent.trim()));
  $("copyLink")?.addEventListener("click", () => copyText($("detailLink").value));
  $("regenBoth")?.addEventListener("click", async () => {
    try {
      await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/regenerate`, { method: "POST", body: JSON.stringify({ target: "both" }) });
      loadClassroom();
    } catch (error) {
      setError(error.message || "Regenerate failed.");
    }
  });
  $("regenCode")?.addEventListener("click", async () => {
    try {
      await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/regenerate`, { method: "POST", body: JSON.stringify({ target: "code" }) });
      loadClassroom();
    } catch (error) {
      setError(error.message || "Regenerate failed.");
    }
  });
  $("archiveToggle")?.addEventListener("click", async () => {
    try {
      await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/archive`, { method: "POST", body: JSON.stringify({}) });
      loadClassroom();
    } catch (error) {
      setError(error.message || "Archive failed.");
    }
  });

  // Activities
  $("activityTermFilter")?.addEventListener("change", renderActivities);
  $("activityForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setError("");
    try {
      await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/activities`, {
        method: "POST",
        body: JSON.stringify({
          title: $("activityTitle").value.trim(),
          term: $("activityTerm")?.value || "Prelim",
          description: $("activityDesc").value.trim(),
          dueDate: $("activityDue").value || null,
        }),
      });
      event.target.reset();
      loadActivities();
      APP.toast("Activity added.");
    } catch (error) {
      setError(error.message || "Failed to add activity.");
    }
  });

  // Submissions
  $("submissionFilter")?.addEventListener("change", renderSubmissions);
  $("exportSubmissions")?.addEventListener("click", () => {
    window.location.href = `/teacher/api/classrooms/${encodeURIComponent(classroomId)}/submissions/export`;
  });
  document.querySelector("[data-close-sub]")?.addEventListener("click", () => $("submissionDialog")?.close());
  $("saveScore")?.addEventListener("click", async () => {
    if (!activeSubmission) return;
    const v = $("subScore").value;
    if (v === "" || Number(v) < 0 || Number(v) > 100) {
      setError("Score must be a number from 0 to 100.");
      return;
    }
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/submissions/${encodeURIComponent(activeSubmission.id)}/score`, {
        method: "POST",
        body: JSON.stringify({ score: Number(v) }),
      });
      activeSubmission = { ...activeSubmission, ...data.submission };
      $("subStatus").textContent = `${data.submission.status} · ${data.submission.score}`;
      loadSubmissions();
      APP.toast("Score saved.");
    } catch (error) {
      setError(error.message || "Failed to save score.");
    }
  });
  $("returnSubmission")?.addEventListener("click", async () => {
    if (!activeSubmission) return;
    try {
      const data = await api(`/teacher/api/classrooms/${encodeURIComponent(classroomId)}/submissions/${encodeURIComponent(activeSubmission.id)}/return`, { method: "POST", body: JSON.stringify({}) });
      activeSubmission = { ...activeSubmission, ...data.submission };
      $("subStatus").textContent = data.submission.status;
      loadSubmissions();
    } catch (error) {
      setError(error.message || "Failed to return submission.");
    }
  });
  $("openGradebook")?.addEventListener("click", () => {
    const studentId = activeSubmission?.studentId;
    $("submissionDialog")?.close();
    document.querySelector('[data-detail-tab="grades"]')?.click();
    if (studentId) {
      const highlight = () => {
        const row = document.querySelector(`[data-grade-row="${CSS.escape(studentId)}"]`);
        if (row) {
          row.scrollIntoView({ block: "center" });
          row.classList.add("bg-emerald-50", "dark:bg-emerald-950/30");
          setTimeout(() => row.classList.remove("bg-emerald-50", "dark:bg-emerald-950/30"), 2500);
        }
      };
      setTimeout(highlight, 600);
      setTimeout(highlight, 1500);
    }
  });

  // Gradebook
  $("loadGradebook")?.addEventListener("click", loadGradebook);

  // Meet-style video (self-hosted LiveKit; degrades gracefully if unconfigured).
  try {
    const section = document.querySelector("[data-video-section]");
    if (section && window.ClassroomVideo) {
      window.ClassroomVideo.init({
        classroomId,
        meetingsUrl: `/teacher/api/classrooms/${encodeURIComponent(classroomId)}/meetings`,
        tokenUrl: `/teacher/api/classrooms/${encodeURIComponent(classroomId)}/video/token`,
        isTeacher: true,
        listEl: document.getElementById("videoMeetings-teacher"),
        errorEl: document.getElementById("videoError-teacher"),
        overlayEl: section.querySelector("[data-video-overlay]"),
        gridEl: section.querySelector("[data-video-grid]"),
        statusEl: section.querySelector("[data-video-status]"),
        joinInstantBtn: section.querySelector("[data-video-join-instant]"),
        scheduleForm: section.querySelector("[data-video-schedule]"),
      });
    }
  } catch (error) {
    console.error("Video initialization failed:", error);
    /* video optional; page works without it */
  }

  wireTabs();
  loadClassroom();
})();
