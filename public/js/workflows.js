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
            return `<tr class="border-t"><td class="p-3">${F.esc(F.userName(s) || r.studentId)}</td><td class="p-3">${F.esc(r.date)}</td><td class="p-3">${F.esc(r.subject || "—")}</td><td class="p-3">${F.esc(r.status)}</td><td class="p-3">${F.esc(r.remarks || "—")}</td>${isTeacher || isAdmin ? `<td class="p-3"><button class="text-emerald-700 font-semibold" data-edit="${F.esc(r.id)}">Edit</button></td>` : ""}</tr>`;
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
          return `<article class="card p-5">
              <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-sm font-bold text-white">${F.esc(initials)}</span>
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
            </article>`;
        }),
      );
    };
    $("#announcementForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
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
      render();
      APP.toast("Announcement published");
    });
    render();
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
        all.map(
          (r) => `<tr class="border-t"><td class="p-3">${F.esc(r.name)}</td><td class="p-3">${F.esc(r.studentId)}</td><td class="p-3">${F.esc(r.status)}</td><td class="p-3">${F.esc(r.dueDate || "—")}</td><td class="p-3"><button class="text-emerald-700 font-semibold" data-approve="${F.esc(r.id)}">Approve</button> <button class="text-rose-700 font-semibold" data-reject="${F.esc(r.id)}">Reject</button></td></tr>`,
        ),
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
