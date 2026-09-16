(() => {
  const U = AUTH.requireRole("teacher");
  if (!U) return;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => APP.esc(value ?? "");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

  let classrooms = [];
  let pendingDeleteId = null;

  const setError = (message) => {
    const box = $("classroomError");
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

  function renderStats() {
    const active = classrooms.filter((c) => c.status === "active");
    const archived = classrooms.filter((c) => c.status !== "active");
    $("classroomTotal").textContent = active.length;
    $("classroomArchived").textContent = archived.length;
    $("classroomStudents").textContent = active.reduce((sum, c) => sum + Number(c.studentsCount || 0), 0);
  }

  function renderGrid() {
    const grid = $("classroomGrid");
    const empty = $("classroomEmpty");
    renderStats();
    if (!classrooms.length) {
      grid.innerHTML = "";
      empty?.classList.remove("hidden");
      return;
    }
    empty?.classList.add("hidden");
    grid.innerHTML = classrooms.map((c) => `
      <div class="group rounded-2xl border border-slate-200 dark:border-slate-700 dark:hover:border-emerald-700 dark:hover:shadow-md">
        <button type="button" data-classroom="${esc(c.id)}" class="block w-full rounded-t-2xl p-4 text-left hover:border-emerald-300 dark:hover:border-emerald-700">
          <div class="flex items-center justify-between gap-2">
            <b class="truncate">${esc(c.name)}</b>
            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ${c.status === "active" ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" : "bg-slate-100 text-slate-500 dark:bg-slate-800"}">${esc(c.status)}</span>
          </div>
          <p class="mt-1 truncate text-xs text-slate-400">${esc(c.subject || "No subject")} · ${Number(c.studentsCount || 0)} student${Number(c.studentsCount || 0) === 1 ? "" : "s"}</p>
          <p class="mt-3 font-mono text-sm font-bold tracking-widest">${esc(c.inviteCodeFormatted || c.inviteCode)}</p>
        </button>
        <div class="mt-1 flex justify-end border-t border-slate-100 px-3 py-2 dark:border-slate-800">
          <button type="button" data-delete-classroom="${esc(c.id)}" title="Delete classroom"
            class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-xs font-semibold text-rose-600 opacity-80 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40">
            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>Delete
          </button>
        </div>
      </div>`).join("");
    grid.querySelectorAll("[data-classroom]").forEach((btn) =>
      btn.addEventListener("click", () => openDetail(btn.dataset.classroom)),
    );
    grid.querySelectorAll("[data-delete-classroom]").forEach((btn) =>
      btn.addEventListener("click", () => showDeleteConfirm(btn.dataset.deleteClassroom)),
    );
    lucide.createIcons();
  }

  async function load() {
    setError("");
    try {
      const data = await api("/teacher/api/classrooms");
      classrooms = data.classrooms || [];
      renderGrid();
    } catch (error) {
      setError(error.message || "Failed to load classrooms.");
    }
  }

  function openDetail(id) {
    window.location.href = `/teacher/classrooms/${encodeURIComponent(id)}`;
  }

  function showDeleteConfirm(id) {
    pendingDeleteId = id;
    const classroom = classrooms.find((c) => String(c.id) === String(id));
    const nameEl = $("deleteClassroomName");
    if (nameEl) nameEl.textContent = classroom?.name || id;
    $("deleteClassroomDialog")?.showModal();
  }

  async function deleteClassroom(id) {
    setError("");
    try {
      await api(`/teacher/api/classrooms/${encodeURIComponent(id)}`, { method: "DELETE" });
      classrooms = classrooms.filter((c) => String(c.id) !== String(id));
      renderGrid();
      APP.toast("Classroom deleted.");
    } catch (error) {
      setError(error.message || "Failed to delete classroom.");
    }
  }

  $("newClassroom")?.addEventListener("click", () => $("classroomDialog")?.showModal());
  document.querySelectorAll("[data-close-classroom]").forEach((btn) =>
    btn.addEventListener("click", () => {
      $("classroomDialog")?.close();
    }),
  );

  $("confirmDeleteClassroom")?.addEventListener("click", async () => {
    if (!pendingDeleteId) return;
    const id = pendingDeleteId;
    pendingDeleteId = null;
    $("deleteClassroomDialog")?.close();
    await deleteClassroom(id);
  });
  document.querySelectorAll("[data-close-delete-classroom]").forEach((btn) =>
    btn.addEventListener("click", () => {
      pendingDeleteId = null;
      $("deleteClassroomDialog")?.close();
    }),
  );
  $("deleteClassroomDialog")?.addEventListener("cancel", () => {
    pendingDeleteId = null;
  });
  $("deleteClassroomDialog")?.addEventListener("click", (event) => {
    if (event.target === event.currentTarget) {
      pendingDeleteId = null;
      $("deleteClassroomDialog")?.close();
    }
  });

  $("classroomForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setError("");
    const name = $("classroomName").value.trim();
    const subject = $("classroomSubject").value.trim();
    if (name.length < 3) {
      setError("Classroom name must be at least 3 characters.");
      return;
    }
    if (subject.length < 3) {
      setError("Subject is required (fixed per classroom, cannot be changed later).");
      return;
    }
    try {
      const data = await api("/teacher/api/classrooms", {
        method: "POST",
        body: JSON.stringify({
          name,
          subject,
          description: $("classroomDescription").value.trim(),
        }),
      });
      classrooms.unshift(data.classroom);
      renderGrid();
      $("classroomDialog")?.close();
      event.target.reset();
      APP.toast("Classroom created — share the code or link.");
      openDetail(data.classroom.id);
    } catch (error) {
      setError(error.message || "Failed to create classroom.");
    }
  });

  // Live updates: reload the classroom list when idle (dialog-safe).
  document.addEventListener("digitech:tick", () => {
    if (!window.DG_SYNC?.idle()) return;
    load();
  });

  load();
})();
