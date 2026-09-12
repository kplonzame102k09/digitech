(function () {
  const F = window.FEATURES;
  const $ = (s) => document.querySelector(s);
  const page = document.body.dataset.feature;
  const U = AUTH.requireRole(document.body.dataset.role);
  if (!U) return;
  const shell = () => {
    APP.applyTheme();
    APP.updateNotif();
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      if (button.dataset.themeBound) return;
      button.dataset.themeBound = "1";
      button.addEventListener("click", () => APP.toggleTheme());
    });
  };
  const users = F.users();
  const renderRows = (rows, empty = "No records yet") => {
    const body = $("#rows");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows.join("")
      : `<tr><td colspan="8" class="p-8 text-center text-slate-500">${F.esc(empty)}</td></tr>`;
    lucide.createIcons();
  };
  function attendance() {
    const isTeacher = U.role === "teacher",
      isAdmin = U.role === "admin";
    let scope = isTeacher
      ? F.teacherStudents(U).map((s) => s.id)
      : isAdmin
        ? F.students().map((s) => s.id)
        : [U.id];
    const render = () => {
      const data = F.get("attendance", []).filter((r) =>
        scope.includes(r.studentId),
      );
      renderRows(
        data
          .sort((a, b) => String(b.date).localeCompare(String(a.date)))
          .map((r) => {
            const s = users.find((u) => u.id === r.studentId);
            const nm = F.userName(s) || r.studentId;
            const initials = (
              nm === r.studentId
                ? nm.slice(0, 2)
                : nm.split(/\s+/).map((w) => w[0]).slice(0, 2).join("")
            ).toUpperCase();
            const [y, m, d] = String(r.date || "").split("-");
            const when =
              y && m && d
                ? new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString("en-PH", {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                  })
                : "—";
            const st = String(r.status || "").toLowerCase();
            const badge =
              {
                present: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
                late: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
                absent: "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
              }[st] || "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300";
            return `<tr class="border-t border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/40">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold text-blue-700 dark:text-blue-300">${F.esc(initials)}</span>
                    ${F.photoUrl(s) ? `<img src="${F.esc(F.photoUrl(s))}" alt="${F.esc(nm)}" loading="lazy" class="absolute inset-0 h-9 w-9 rounded-full object-cover" onerror="this.style.display='none'" />` : ""}
                  </span>
                  <div>
                    <b class="block">${F.esc(nm)}</b>
                    <small class="text-xs text-slate-400">${F.esc(r.studentId)}</small>
                  </div>
                </div>
              </td>
              <td class="whitespace-nowrap p-4 text-xs font-medium text-slate-600 dark:text-slate-400">${F.esc(when)}</td>
              <td class="p-4 text-slate-700 dark:text-slate-300">${F.esc(r.subject || "—")}</td>
              <td class="p-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${badge}">${F.esc(r.status || "—")}</span></td>
              <td class="max-w-[16rem] truncate p-4 text-slate-500 dark:text-slate-400" title="${F.esc(r.remarks || "")}">${F.esc(r.remarks || "—")}</td>
              ${isTeacher || isAdmin ? `<td class="p-4 text-right"><button class="rounded-lg p-2 text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/40" data-edit="${F.esc(r.id)}" title="Edit record"><i data-lucide="pencil" class="h-4 w-4"></i></button></td>` : `<td class="p-4"></td>`}
            </tr>`;
          }),
      );
      $("#rows")
        ?.querySelectorAll("[data-edit]")
        .forEach((b) => (b.onclick = () => editAttendance(b.dataset.edit)));
    };
    const editAttendance = (id) => {
      const all = F.get("attendance", []);
      const r = all.find((x) => x.id === id) || {
        id: DG.generateId("ATT"),
        studentId: isTeacher ? scope[0] : $("#student")?.value,
        date: new Date().toISOString().slice(0, 10),
      };
      const student = $("#student");
      if (student) {
        student.innerHTML = (isTeacher ? F.teacherStudents(U) : F.students())
          .map(
            (s) => `<option value="${F.esc(s.id)}">${F.esc(F.userName(s))}</option>`,
          )
          .join("");
        student.value = r.studentId;
      }
      ["recordId", "date", "subject", "remarks"].forEach((k) => {
        const el = $("#" + k);
        if (el) el.value = k === "recordId" ? r.id : r[k] || "";
      });
      $("#status").value = r.status || "Present";
      $("#attendanceDialog")?.classList.remove("hidden");
    };
    $("#newAttendance")?.addEventListener("click", () => editAttendance(""));
    $("#closeAttendance")?.addEventListener("click", () =>
      $("#attendanceDialog")?.classList.add("hidden"),
    );
    $("#attendanceForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const all = F.get("attendance", []);
      const r = {
        id: $("#recordId").value || DG.generateId("ATT"),
        studentId: $("#student").value,
        date: $("#date").value,
        subject: $("#subject").value.trim(),
        status: $("#status").value,
        remarks: $("#remarks").value.trim(),
        recordedBy: U.id,
        updatedAt: new Date().toISOString(),
      };
      const i = all.findIndex((x) => x.id === r.id);
      i >= 0 ? (all[i] = r) : all.push(r);
      F.save("attendance", all);
      F.notify(
        [r.studentId, ...F.parentIdsFor([r.studentId])],
        "Attendance updated",
        `${r.date}: ${r.status}`,
        "attendance",
      );
      $("#attendanceDialog").classList.add("hidden");
      render();
      APP.toast("Attendance saved");
    });
    $("#export")?.addEventListener("click", () => {
      const data = F.get("attendance", []).filter((r) =>
        scope.includes(r.studentId),
      );
      F.download(
        "attendance.csv",
        F.csv(
          ["Student ID", "Date", "Subject", "Status", "Remarks"],
          data.map((r) => [
            r.studentId,
            r.date,
            r.subject,
            r.status,
            r.remarks,
          ]),
        ),
        "text/csv",
      );
    });
    render();
  }
  function announcements() {
    const canCreate = U.role === "admin" || U.role === "teacher";
    const AUDIENCE_BADGE = {
      All: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
      Students: "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
      Parents: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
      Teachers: "bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
      Guests: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300",
    };
    const renderFeed = (cards, empty = "No announcements yet") => {
      const body = $("#rows");
      if (!body) return;
      body.innerHTML = cards.length
        ? cards.join("")
        : `<div class="card p-10 text-center text-sm text-slate-500">${F.esc(empty)}</div>`;
      lucide.createIcons();
    };
    const render = () => {
      const audience =
        U.role === "student"
          ? "Students"
          : U.role === "parent"
            ? "Parents"
            : U.role === "teacher"
              ? "Teachers"
              : "All";
      const rows = F.get("announcements", [])
        .filter(
          (r) =>
            r.authorId === U.id ||
            r.createdBy === U.id ||
            r.audience === "All" ||
            r.audience === audience,
        )
        .sort(
          (a, b) =>
            new Date(b.createdAt || b.date) - new Date(a.createdAt || a.date),
        );
      renderFeed(
        rows.map((r) => {
          const author = users.find(
            (u) => u.id === (r.createdBy || r.authorId),
          );
          const name = F.userName(author) || "College Office";
          const initials = author
            ? `${(author.firstName || "")[0] || ""}${(author.lastName || "")[0] || ""}`.toUpperCase() || "DG"
            : "DT";
          const aud = r.audience || "All";
          const badge =
            AUDIENCE_BADGE[aud] ||
            "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300";
          const authorPhoto = F.photoUrl(author);
          const avatar = authorPhoto
            ? `<img src="${F.esc(authorPhoto)}" alt="${F.esc(name)}" class="h-11 w-11 shrink-0 rounded-full object-cover" onerror="this.style.display='none'" />`
            : `<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-sm font-bold text-white">${F.esc(initials)}</span>`;
          return `<article class="card p-5">
              <div class="flex items-start gap-3">
                ${avatar}
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                    <div class="flex items-center gap-2">
                      <span class="font-bold">${F.esc(name)}</span>
                      <span class="text-slate-400">·</span>
                      <time class="text-xs text-slate-400">${F.esc(APP.formatDate(r.createdAt || r.date))}</time>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ${badge}">${F.esc(aud)}</span>
                  </div>
                  <span class="mt-1 inline-block rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">${F.esc(r.category || "General")}</span>
                </div>
              </div>
              <h3 class="mt-3 text-lg font-bold tracking-tight">${F.esc(r.title)}</h3>
              <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">${F.esc(r.message)}</p>
              ${r.image ? `<img src="${F.esc(r.image)}" alt="${F.esc(r.title)}" class="mt-3 max-h-80 w-full rounded-xl border border-slate-200 object-cover dark:border-slate-700" onerror="this.style.display='none'" />` : ""}
            </article>`;
        }),
      );
    };
    $("#announcementForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const imageInput = $("#announcementImage");
      let image = null;
      if (imageInput?.files?.length) {
        imageInput.disabled = true;
        try {
          const uploaded = await DG.uploadImage(imageInput.files[0]);
          image = uploaded.photo || null;
        } catch (error) {
          console.error("Failed to upload announcement photo:", error);
          APP.toast("Photo upload failed. Try a smaller image.", "error");
          imageInput.disabled = false;
          return;
        }
        imageInput.disabled = false;
      }
      const audience = $("#audience").value;
      const assignedIds =
        U.role === "teacher"
          ? F.teacherStudents(U).map((s) => s.id)
          : users.filter((u) => u.role !== "admin").map((u) => u.id);
      const teacherParentIds =
        U.role === "teacher" ? F.parentIdsFor(assignedIds) : [];
      const audienceRoles = {
        All: null,
        Students: ["student"],
        Parents: ["parent"],
        Teachers: ["teacher"],
        Guests: ["guest"],
      };
      const ids = users
        .filter((u) =>
          U.role === "teacher"
            ? u.id === U.id ||
              assignedIds.includes(u.id) ||
              teacherParentIds.includes(u.id)
            : audience === "All" ||
              (audienceRoles[audience] || []).includes(u.role),
        )
        .map((u) => u.id);
      const a = {
        id: DG.generateId("ANN"),
        title: $("#title").value.trim(),
        message: $("#message").value.trim(),
        category: $("#category").value.trim() || "General",
        audience,
        createdBy: U.id,
        authorId: U.id,
        image,
        createdAt: new Date().toISOString(),
      };
      const all = F.get("announcements", []);
      all.push(a);
      F.save("announcements", all);
      F.notify(
        ids.filter((id) => id !== U.id),
        a.title,
        a.message,
        "announcement",
      );
      e.target.reset();
      resetPhotoUI();
      render();
      APP.toast("Announcement published");
    });
    const resetPhotoUI = () => {
      const imageInput = $("#announcementImage");
      if (imageInput) imageInput.value = "";
      const nameEl = $("#announcementImageName");
      if (nameEl) nameEl.textContent = "";
      const preview = $("#announcementImagePreview");
      if (preview) preview.classList.add("hidden");
    };
    const imageInput = $("#announcementImage");
    imageInput?.addEventListener("change", () => {
      const file = imageInput.files?.[0];
      const nameEl = $("#announcementImageName");
      const preview = $("#announcementImagePreview");
      const previewImg = $("#announcementImagePreviewImg");
      if (!file) {
        resetPhotoUI();
        return;
      }
      if (nameEl) nameEl.textContent = file.name;
      const reader = new FileReader();
      reader.onload = () => {
        if (previewImg) previewImg.src = reader.result;
        if (preview) preview.classList.remove("hidden");
        lucide.createIcons();
      };
      reader.readAsDataURL(file);
    });
    $("#announcementImageRemove")?.addEventListener("click", resetPhotoUI);
    render();
  }
  function safePreviewUrl(url) {
    const value = String(url || "").trim();
    if (!value) return null;
    if (value.startsWith("/api/portal/requirements/files/")) return value;
    if (/^https?:\/\//i.test(value)) return value;
    if (value.startsWith("//")) return "https:" + value;
    return null;
  }

  function openRequirementPreview(r) {
    const root = document.getElementById("modalRoot");
    if (!root) return;
    APP.closeModal();
    const url = safePreviewUrl(r.fileUrl || "");
    const hasPreview = url !== null;
    const ext = (url || "").split("?")[0].toLowerCase();
    const isImage = hasPreview && /\.(jpe?g|png|webp|gif)$/.test(ext);
    const isPdf = hasPreview && ext.endsWith(".pdf");
    const student = F.users().find((u) => u.id === r.studentId);
    const backdrop = document.createElement("div");
    backdrop.className =
      "modal-backdrop fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4";
    const panel = document.createElement("div");
    panel.className =
      "w-full max-w-2xl rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900";
    const close = () => APP.closeModal();
    panel.innerHTML = `
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">Requirement submission</p>
          <h2 class="mt-1 truncate text-lg font-extrabold">${F.esc(r.name)}</h2>
          <p class="mt-1 text-xs text-slate-500">${F.esc(student ? F.userName(student) : r.studentId)} · ${F.esc(r.status)}</p>
        </div>
        <button type="button" data-preview-close class="shrink-0 rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close"><i data-lucide="x" class="h-5 w-5"></i></button>
      </div>
      <div class="mt-4 max-h-[65vh] overflow-auto rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
        ${!hasPreview ? `<div class="flex flex-col items-center gap-2 p-10 text-center"><i data-lucide="file-x" class="h-8 w-8 text-slate-400"></i><p class="text-sm text-slate-500">No file attached or the attached file cannot be previewed.</p></div>` : isImage ? `<img src="${F.esc(url)}" alt="${F.esc(r.name)}" class="mx-auto max-h-[58vh] object-contain">` : isPdf ? `<iframe src="${F.esc(url)}" title="${F.esc(r.name)}" class="h-[58vh] w-full"></iframe>` : `<div class="flex flex-col items-center gap-2 p-10 text-center"><i data-lucide="file-text" class="h-8 w-8 text-slate-400"></i><p class="text-sm text-slate-500">No inline preview for this file type.</p></div>`}
      </div>
      <div class="mt-4 flex justify-end gap-3">
        ${hasPreview ? `<a href="${F.esc(url)}" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold dark:border-slate-700">Open original</a>` : ""}
        <button type="button" data-preview-close class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Close</button>
      </div>`;
    panel.querySelectorAll("[data-preview-close]").forEach((b) => b.addEventListener("click", close));
    backdrop.append(panel);
    root.append(backdrop);
    lucide.createIcons();
  }

  function requirements() {
    const studentSelect = $("#student");
    if (studentSelect) {
      studentSelect.innerHTML =
        (U.role === "admin" ? F.students() : F.teacherStudents(U))
          .map(
            (s) =>
              `<option value="${F.esc(s.id)}">${F.esc(F.userName(s))}</option>`,
          )
          .join("");
    }
    const render = () => {
      const all = F.get("requirements", []);
      renderRows(
        all.map((r) => {
          const actions = [];
          if (r.fileUrl) {
            actions.push(
              `<button class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-blue-700" data-preview="${F.esc(r.id)}"><i data-lucide="eye" class="h-3.5 w-3.5"></i>Preview</button>`,
            );
          }
          if (r.status === "Submitted") {
            actions.push(
              `<button class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-700" data-approve="${F.esc(r.id)}"><i data-lucide="check" class="h-3.5 w-3.5"></i>Approve</button>`,
              `<button class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-rose-700" data-reject="${F.esc(r.id)}"><i data-lucide="x" class="h-3.5 w-3.5"></i>Reject</button>`,
            );
          }
          const actionCell = actions.length ? `<div class="flex flex-wrap items-center gap-2">${actions.join("")}</div>` : "—";
          return `<tr class="border-t"><td class="p-3">${F.esc(r.name)}</td><td class="p-3">${F.esc(r.studentId)}</td><td class="p-3">${F.esc(r.status)}</td><td class="p-3">${F.esc(r.dueDate || "—")}</td><td class="p-3">${actionCell}</td></tr>`;
        }),
      );
      $("#rows")
        ?.querySelectorAll("[data-approve],[data-reject]")
        .forEach(
          (b) =>
            (b.onclick = () => {
              const a = F.get("requirements", []),
                r = a.find(
                  (x) =>
                    x.id === b.dataset.approve || x.id === b.dataset.reject,
                );
              if (!r) return;
              r.status = b.dataset.approve ? "Approved" : "Rejected";
              r.reviewedBy = U.id;
              r.reviewedAt = new Date().toISOString();
              F.save("requirements", a);
              F.notify(
                [r.studentId, ...F.parentIdsFor([r.studentId])],
                `Requirement ${r.status}`,
                `${r.name} was ${r.status.toLowerCase()}.`,
                "requirement",
              );
              render();
              APP.toast(`Requirement ${r.status.toLowerCase()}`);
            }),
        );
      $("#rows")
        ?.querySelectorAll("[data-preview]")
        .forEach(
          (b) =>
            (b.onclick = () => {
              const r = F.get("requirements", []).find(
                (x) => x.id === b.dataset.preview,
              );
              if (r) openRequirementPreview(r);
            }),
        );
    };
   $("#requirementForm")?.addEventListener("submit", (e) => {
  e.preventDefault();

  const all = F.get("requirements", []);
  const selected = $("#student").value;
  const requirementName = $("#name").value.trim();

  all.push({
    id: DG.generateId("REQ"),
    name: requirementName,
    studentId: selected,
    dueDate: $("#dueDate").value,
    status: "Pending",
    createdAt: new Date().toISOString(),
  });

  // Save the assigned requirement
  F.save("requirements", all);

  // Notify the student and their parent(s)
  F.notify(
    [selected, ...F.parentIdsFor([selected])],
    "New Requirement Assigned",
    `${requirementName} has been assigned to you.`,
    "requirement"
  );

  e.target.reset();
  render();
  APP.toast("Requirement assigned");
});

render();
  }
  shell();
  if (page === "attendance") attendance();
  if (page === "announcements") announcements();
  if (page === "requirements") requirements();
})();
