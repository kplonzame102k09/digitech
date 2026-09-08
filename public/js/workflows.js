(function () {
  const F = window.FEATURES;
  const $ = (s) => document.querySelector(s);
  const page = document.body.dataset.feature;
  const U = AUTH.requireRole(document.body.dataset.role);
  if (!U) return;
  const shell = () => {
    APP.applyTheme();
    APP.updateNotif();
    $("#logout")?.addEventListener("click", () => AUTH.logout());
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
            r.createdBy === U.id ||
            r.audience === "All" ||
            r.audience === audience,
        )
        .sort(
          (a, b) =>
            new Date(b.createdAt || b.date) - new Date(a.createdAt || a.date),
        );
      renderRows(
        rows.map(
          (r) => `<tr class="border-t"><td class="p-3 font-semibold">${F.esc(r.title)}</td><td class="p-3">${F.esc(r.audience || "All")}</td><td class="p-3">${F.esc(r.category || "General")}</td><td class="p-3">${F.esc(APP.formatDate(r.createdAt || r.date))}</td><td class="p-3">${F.esc(r.message)}</td></tr>`,
        ),
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
      const ids = users
        .filter((u) =>
          U.role === "teacher"
            ? u.id === U.id ||
              assignedIds.includes(u.id) ||
              teacherParentIds.includes(u.id)
            : audience === "All" || u.role === audience.toLowerCase(),
        )
        .map((u) => u.id);
      const a = {
        id: DG.generateId("ANN"),
        title: $("#title").value.trim(),
        message: $("#message").value.trim(),
        category: $("#category").value.trim() || "General",
        audience,
        createdBy: U.id,
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
