(() => {
  const U = AUTH.requireRole("teacher");
  if (!U) return;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => APP.esc(value ?? "");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

  let classrooms = [];

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
      <button type="button" data-classroom="${esc(c.id)}" class="rounded-2xl border border-slate-200 p-4 text-left hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700">
        <div class="flex items-center justify-between gap-2">
          <b class="truncate">${esc(c.name)}</b>
          <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ${c.status === "active" ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" : "bg-slate-100 text-slate-500 dark:bg-slate-800"}">${esc(c.status)}</span>
        </div>
        <p class="mt-1 truncate text-xs text-slate-400">${esc(c.subject || "No subject")} · ${Number(c.studentsCount || 0)} student${Number(c.studentsCount || 0) === 1 ? "" : "s"}</p>
        <p class="mt-3 font-mono text-sm font-bold tracking-widest">${esc(c.inviteCodeFormatted || c.inviteCode)}</p>
      </button>`).join("");
    grid.querySelectorAll("[data-classroom]").forEach((btn) =>
      btn.addEventListener("click", () => openDetail(btn.dataset.classroom)),
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

  $("newClassroom")?.addEventListener("click", () => $("classroomDialog")?.showModal());
  document.querySelectorAll("[data-close-classroom]").forEach((btn) =>
    btn.addEventListener("click", () => {
      $("classroomDialog")?.close();
    }),
  );

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

  load();
})();
