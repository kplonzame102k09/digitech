(() => {
  const U = AUTH.requireRole("student");
  if (!U) return;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => APP.esc(value ?? "");
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

  const setError = (message) => {
    const box = $("joinError");
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

  function renderMine(classrooms) {
    const wrap = $("myClassrooms");
    const empty = $("myEmpty");
    if (!classrooms.length) {
      wrap.innerHTML = "";
      empty?.classList.remove("hidden");
      return;
    }
    empty?.classList.add("hidden");
    wrap.innerHTML = classrooms.map((c) => `
      <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
        <b class="block">${esc(c.name)}</b>
        <p class="mt-1 text-xs text-slate-400">${esc(c.subject || "No subject")} · ${esc(c.teacherName || "Your teacher")}</p>
        ${c.description ? `<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">${esc(c.description)}</p>` : ""}
        <div class="mt-3 flex flex-wrap gap-2">
          <a href="/student/classrooms/${esc(c.id)}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white dark:bg-slate-100 dark:text-slate-900">Open classroom</a>
          <button type="button" data-view-activities="${esc(c.id)}" class="rounded-xl border px-4 py-2 text-xs font-semibold">Quick submit</button>
        </div>
        <div data-activities-for="${esc(c.id)}" class="mt-3 hidden space-y-3"></div>
      </div>`).join("");
    wrap.querySelectorAll("[data-view-activities]").forEach((btn) =>
      btn.addEventListener("click", () => toggleActivities(btn.dataset.viewActivities, btn)),
    );
  }

  async function toggleActivities(classroomId, btn) {
    const box = document.querySelector(`[data-activities-for="${CSS.escape(classroomId)}"]`);
    if (!box) return;
    if (!box.classList.contains("hidden")) {
      box.classList.add("hidden");
      box.innerHTML = "";
      btn.textContent = "Quick submit";
      return;
    }
    btn.textContent = "Hide quick submit";
    box.classList.remove("hidden");
    box.innerHTML = `<p class="text-sm text-slate-500">Loading…</p>`;
    try {
      const data = await api(`/student/api/classrooms/${encodeURIComponent(classroomId)}/activities`);
      const list = data.activities || [];
      const groups = ["Prelim", "Midterm", "Finals"].map((term) => ({
        term,
        items: list.filter((a) => (a.term || "Prelim") === term),
      })).filter((g) => g.items.length);
      const groupsHtml = groups.length
        ? groups.map((g) => `
          <div>
            <p class="mt-3 text-xs font-bold uppercase tracking-wider text-slate-400">${g.term}</p>
            <div class="mt-1 space-y-3">${g.items.map((a) => {
            const sub = a.submission;
            return `<div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
              <b class="block text-sm">${esc(a.title)}</b>
              ${a.description ? `<p class="mt-0.5 text-xs text-slate-500">${esc(a.description)}</p>` : ""}
              <p class="mt-1 text-[11px] text-slate-400">${a.dueDate ? `Due ${esc(a.dueDate)} · ` : ""}${sub ? `${esc(sub.status)}${sub.score !== null && sub.score !== undefined ? ` · ${esc(sub.score)}` : ""}` : "Not submitted"}</p>
              <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <input type="file" data-file-for="${esc(a.id)}" accept=".pdf,image/jpeg,image/png,image/webp" class="text-xs" />
                <input type="text" data-notes-for="${esc(a.id)}" maxlength="2000" placeholder="Notes (optional)" class="input flex-1 rounded-lg border px-2 py-1.5 text-xs" />
                <button type="button" data-submit-activity="${esc(a.id)}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">${sub ? "Re-submit" : "Submit"}</button>
              </div>
            </div>`;
          }).join("")}</div>
          </div>`).join("")
        : `<p class="text-sm text-slate-500">No activities yet.</p>`;
      box.innerHTML = groupsHtml;
      box.querySelectorAll("[data-submit-activity]").forEach((btn2) =>
        btn2.addEventListener("click", () => submitActivity(btn2.dataset.submitActivity, classroomId, box)),
      );
    } catch (error) {
      box.innerHTML = `<p class="text-sm text-rose-600">${esc(error.message || "Failed to load activities.")}</p>`;
    }
  }

  async function uploadFile(file, classroomId) {
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

  async function submitActivity(activityId, classroomId, box) {
    setError("");
    const fileInput = box.querySelector(`[data-file-for="${CSS.escape(activityId)}"]`);
    const notesInput = box.querySelector(`[data-notes-for="${CSS.escape(activityId)}"]`);
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
      const fileUrl = await uploadFile(file, classroomId);
      await api(`/student/api/activities/${encodeURIComponent(activityId)}/submit`, {
        method: "POST",
        body: JSON.stringify({ fileUrl, notes: notesInput?.value?.trim() || null }),
      });
      APP.toast("Submitted!");
      const btn = document.querySelector(`[data-view-activities="${CSS.escape(classroomId)}"]`);
      box.classList.add("hidden");
      box.innerHTML = "";
      if (btn) toggleActivities(classroomId, btn);
    } catch (error) {
      setError(error.message || "Submit failed.");
    }
  }

  async function load() {
    try {
      const data = await api("/student/api/classrooms");
      renderMine(data.classrooms || []);
    } catch (error) {
      setError(error.message || "Failed to load classrooms.");
    }
  }

  const codeInput = $("joinCode");
  codeInput?.addEventListener("input", () => {
    const digits = codeInput.value.replace(/\D/g, "").slice(0, 9);
    codeInput.value = digits.length > 6
      ? `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6)}`
      : digits.length > 3
        ? `${digits.slice(0, 3)}-${digits.slice(3)}`
        : digits;
  });

  $("joinForm")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setError("");
    const code = codeInput.value.trim();
    if (code.replace(/\D/g, "").length !== 9) {
      setError("Class codes are 9 digits.");
      return;
    }
    try {
      const data = await api("/student/api/classrooms/join", {
        method: "POST",
        body: JSON.stringify({ code }),
      });
      APP.toast(data.alreadyJoined ? "You are already in this classroom." : "Joined classroom!");
      codeInput.value = "";
      load();
    } catch (error) {
      setError(error.message || "Could not join classroom.");
    }
  });

  load();
})();
