(() => {
  const U = AUTH.requireRole("teacher");
  if (!U) return;

  const $ = (id) => document.getElementById(id);
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const esc = (value) => APP.esc(value ?? "");
  const page = () => location.pathname.split("/").pop();
  const statuses = ["Not Started", "In Progress", "Competent", "Not Yet Competent"];
  const publicationStates = ["Published", "Unpublished"];

  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    user?.id ||
    "Unknown";

  const latestEnrollment = (studentId, enrollments) =>
    enrollments
      .filter((record) => record.studentId === studentId)
      .sort((a, b) =>
        String(b.updatedAt || b.createdAt || "").localeCompare(
          String(a.updatedAt || a.createdAt || ""),
        ),
      )[0];

  const studentFor = (id, users = get("users", [])) =>
    users.find((user) => user.id === id);

  const assignedData = () => {
    const users = get("users", []);
    const enrollments = get("enrollments", []);
    const grades = get("grades", []);
    const competencies = get("competencies", []);
    const teacherName = fullName(U);
    const ids = new Set(
      enrollments
        .filter(
          (record) =>
            record.assignedTeacherId === U.id ||
            record.assignedTeacher === U.id ||
            record.teacherId === U.id ||
            record.teacher === teacherName,
        )
        .map((record) => record.studentId)
        .filter(Boolean),
    );

    grades
      .filter(
        (record) => record.teacherId === U.id || record.teacher === teacherName,
      )
      .forEach((record) => ids.add(record.studentId));

    competencies
      .filter(
        (record) =>
          record.teacherId === U.id ||
          record.assessorId === U.id ||
          record.assessor === teacherName,
      )
      .forEach((record) => ids.add(record.studentId));

    return { ids, users, enrollments, grades, competencies };
  };

  const assignedStudents = (data = assignedData()) =>
    data.users
      .filter((user) => user.role === "student" && data.ids.has(user.id))
      .sort((a, b) => fullName(a).localeCompare(fullName(b)));

  const notify = (studentId, title, message, source, recordId) => {
    const users = get("users", []);
    const targets = users.filter(
      (user) =>
        user.id === studentId ||
        (user.role === "parent" &&
          [user.childId, ...(user.childIds || []), ...(user.children || [])].some(
            (child) =>
              (typeof child === "object"
                ? child.id || child.studentId
                : child) === studentId,
          )),
    );
    const notifications = get("notifications", []);
    targets.forEach((user) =>
      notifications.push({
        id: DG.generateId("NOT"),
        userId: user.id,
        title,
        message,
        date: new Date().toISOString(),
        read: false,
        source,
        [`${source}Id`]: recordId,
      }),
    );
    save("notifications", notifications);
  };

  const audit = (entity, record, action, notes) => {
    const logs = get("auditLogs", []);
    logs.push({
      id: DG.generateId("AUD"),
      entity,
      recordId:
        record.id || `${record.studentId}-${record.subject || record.competency}`,
      action,
      actorId: U.id,
      notes: notes || "",
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  };

  const setText = (id, value) => {
    const element = $(id);
    if (element) element.textContent = value ?? "";
  };

  const renderMetricCards = (id, metrics) => {
    const container = $(id);
    if (!container) return;
    container.innerHTML = metrics
      .map(
        (metric) => `
          <div class="teacher-stat card p-5">
            <div class="flex items-start justify-between gap-3">
              <span class="teacher-stat-icon ${metric.iconClass}"><i data-lucide="${metric.icon}"></i></span>
              <span class="text-xs font-semibold text-slate-400">${esc(metric.note || "")}</span>
            </div>
            <p class="mt-5 text-sm font-medium text-slate-500 dark:text-slate-400">${esc(metric.label)}</p>
            <p class="mt-1 text-3xl font-extrabold tracking-tight">${esc(metric.value)}</p>
          </div>`,
      )
      .join("");
    lucide.createIcons();
  };

  const statusBadge = (status) => {
    const value = status || "Not Started";
    const styles = {
      Competent: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300",
      "In Progress": "bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300",
      "Not Yet Competent": "bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300",
      "Not Started": "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300",
    };
    return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${styles[value] || styles["Not Started"]}">${esc(value)}</span>`;
  };

  const publicationBadge = (published) =>
    published
      ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Published</span>'
      : '<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Unpublished</span>';

  const emptyRows = (target, title, text, colspan = 8) => {
    if (!target) return;
    target.innerHTML = `<tr><td colspan="${colspan}" class="p-10 text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><i data-lucide="inbox"></i></div><div class="mt-4 font-semibold">${esc(title)}</div><p class="mt-1 text-sm text-slate-400">${esc(text)}</p></td></tr>`;
    lucide.createIcons();
  };

  function setup() {
    APP.applyTheme();
    APP.updateNotif();
    lucide.createIcons();

    $("open")?.addEventListener("click", () => {
      $("side")?.classList.toggle("-translate-x-full");
    });

    $("side")?.querySelectorAll("a").forEach((link) =>
      link.addEventListener("click", () => {
        if (window.innerWidth < 1024) $("side")?.classList.add("-translate-x-full");
      }),
    );

    const avatar = $("avatar");
    if (avatar) {
      avatar.src = DG.getProfilePhoto(U);
      avatar.alt = `${fullName(U)} profile photo`;
    }
  }

  function studentModal(studentId) {
    const data = assignedData();
    const student = studentFor(studentId, data.users);
    if (!student) return;

    const enrollment = latestEnrollment(studentId, data.enrollments);
    const requirements = get("requirements", []).filter(
      (record) => record.studentId === studentId,
    );
    const documents = get("documentRequests", []).filter(
      (record) => record.studentId === studentId,
    );
    const grades = data.grades
      .filter((record) => record.studentId === studentId)
      .sort((a, b) => String(a.subject || "").localeCompare(String(b.subject || "")));
    const competencies = data.competencies.filter(
      (record) => record.studentId === studentId,
    );
    const average = grades.filter((record) => Number.isFinite(Number(record.grade)));
    const averageGrade = average.length
      ? (average.reduce((sum, record) => sum + Number(record.grade), 0) / average.length).toFixed(2)
      : "—";
    const root = $("modalRoot") || document.body;
    root.replaceChildren();
    root.innerHTML = `
      <div class="teacher-modal-backdrop fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Student details">
        <div class="teacher-modal-panel max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-blue-600 text-lg font-extrabold text-white">${esc(`${student.firstName?.[0] || ""}${student.lastName?.[0] || ""}`.toUpperCase() || "ST")}</div>
              <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Student snapshot</p><h2 class="mt-1 text-2xl font-extrabold">${esc(fullName(student))}</h2><p class="mt-1 text-sm text-slate-500">${esc(student.id)} · ${esc(student.email || "No email")}</p></div>
            </div>
            <button type="button" data-close-modal class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close student details"><i data-lucide="x"></i></button>
          </div>
          <div class="mt-7 grid gap-3 sm:grid-cols-4">
            <div class="teacher-detail-card"><p>Enrollment</p><b>${esc(enrollment?.status || "No enrollment")}</b><small>${esc([enrollment?.schoolYear, enrollment?.assignedSection].filter(Boolean).join(" · ") || "—")}</small></div>
            <div class="teacher-detail-card"><p>Grade average</p><b>${esc(averageGrade)}</b><small>${average.length} recorded subject${average.length === 1 ? "" : "s"}</small></div>
            <div class="teacher-detail-card"><p>Requirements</p><b>${requirements.length}</b><small>Submitted or tracked</small></div>
            <div class="teacher-detail-card"><p>Competencies</p><b>${competencies.length}</b><small>${competencies.filter((record) => record.status === "Competent").length} competent</small></div>
          </div>
          <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <section><div class="flex items-center justify-between"><h3 class="font-bold">Academic records</h3><span class="text-xs text-slate-400">${grades.length} total</span></div><div class="mt-3 space-y-2">${grades.map((record) => `<div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-700"><div><p class="font-semibold">${esc(record.subject || "Subject")}</p><p class="text-xs text-slate-400">${esc(record.term || record.period || "No term")}</p></div><b class="text-lg ${record.grade === null || record.grade === undefined || record.grade === "" ? "text-slate-400" : "text-blue-600"}">${esc(record.grade ?? "—")}</b></div>`).join("") || '<p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-400 dark:bg-slate-800">No grade records yet.</p>'}</div></section>
            <section><div class="flex items-center justify-between"><h3 class="font-bold">Competency progress</h3><span class="text-xs text-slate-400">${competencies.length} total</span></div><div class="mt-3 space-y-2">${competencies.map((record) => `<div class="rounded-2xl border border-slate-200 p-3 dark:border-slate-700"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">${esc(record.competency || "Competency")}</p><p class="text-xs text-slate-400">${esc(record.qualification || "No qualification")}</p></div>${statusBadge(record.status)}</div><p class="mt-2 text-xs text-slate-500">${esc(record.remarks || record.evidence || "No assessment notes")}</p></div>`).join("") || '<p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-400 dark:bg-slate-800">No competency records yet.</p>'}</div></section>
          </div>
          <div class="mt-8 flex justify-end"><button type="button" data-close-modal class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900">Close details</button></div>
        </div>
      </div>`;
    root.querySelectorAll("[data-close-modal]").forEach((button) =>
      button.addEventListener("click", () => root.replaceChildren()),
    );
    root.firstElementChild?.addEventListener("click", (event) => {
      if (event.target === root.firstElementChild) root.replaceChildren();
    });
    lucide.createIcons();
  }

  function renderDashboard() {
    const data = assignedData();
    const students = assignedStudents(data);
    const pendingGrades = data.grades.filter(
      (record) =>
        data.ids.has(record.studentId) &&
        (record.grade === null || record.grade === undefined || record.grade === ""),
    );
    const competencyRecords = data.competencies.filter((record) =>
      data.ids.has(record.studentId),
    );
    const needsAssessment = competencyRecords.filter(
      (record) => !record.status || record.status === "Not Started" || record.status === "Not Yet Competent",
    );
    const unread = get("notifications", []).filter(
      (notification) => notification.userId === U.id && !notification.read,
    ).length;

    setText("name", U.firstName || fullName(U));
    renderMetricCards("dashboardStats", [
      { label: "Assigned students", value: students.length, note: "Active roster", icon: "users-round", iconClass: "bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300" },
      { label: "Grades to review", value: pendingGrades.length, note: pendingGrades.length ? "Needs attention" : "All caught up", icon: "file-pen-line", iconClass: "bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300" },
      { label: "Competency records", value: competencyRecords.length, note: `${needsAssessment.length} need action`, icon: "award", iconClass: "bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300" },
      { label: "Unread notifications", value: unread, note: unread ? "Review updates" : "No new alerts", icon: "bell-ring", iconClass: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300" },
    ]);

    const list = $("list");
    if (list) {
      list.innerHTML = students.slice(0, 6).map((student) => {
        const enrollment = latestEnrollment(student.id, data.enrollments);
        const studentGrades = data.grades.filter((record) => record.studentId === student.id);
        const studentComps = data.competencies.filter((record) => record.studentId === student.id);
        return `<button type="button" data-student="${esc(student.id)}" class="teacher-student-card group w-full rounded-2xl border border-slate-200 p-4 text-left hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700"><div class="flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-100 to-blue-100 text-sm font-extrabold text-emerald-700 dark:from-emerald-950 dark:to-blue-950 dark:text-emerald-300">${esc(`${student.firstName?.[0] || ""}${student.lastName?.[0] || ""}`.toUpperCase() || "ST")}</span><div class="min-w-0"><p class="truncate font-semibold">${esc(fullName(student))}</p><p class="truncate text-xs text-slate-400">${esc(student.id)}</p></div></div><i data-lucide="arrow-up-right" class="h-4 w-4 text-slate-400 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5"></i></div><div class="mt-4 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500 dark:bg-slate-800">${esc(enrollment?.status || "No enrollment")}</span><span class="rounded-full bg-blue-50 px-2.5 py-1 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">${studentGrades.length} grade${studentGrades.length === 1 ? "" : "s"}</span><span class="rounded-full bg-violet-50 px-2.5 py-1 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">${studentComps.length} competency${studentComps.length === 1 ? "" : "ies"}</span></div></button>`;
      }).join("") || `<div class="teacher-empty-state md:col-span-2"><i data-lucide="users-round"></i><b>No assigned students yet</b><p>Students connected to your enrollment, grade, or competency records will appear here.</p></div>`;
      list.querySelectorAll("[data-student]").forEach((button) =>
        button.addEventListener("click", () => studentModal(button.dataset.student)),
      );
      lucide.createIcons();
    }

    const queue = $("dashboardQueue");
    if (queue) {
      const queueItems = [
        ...pendingGrades.slice(0, 3).map((record) => ({ icon: "file-pen-line", color: "text-amber-600", label: `${studentFor(record.studentId, data.users)?.firstName || "Student"} · ${record.subject || "Grade"}`, text: "Grade is still blank", href: "grades.html" })),
        ...needsAssessment.slice(0, 3).map((record) => ({ icon: "award", color: "text-violet-600", label: `${studentFor(record.studentId, data.users)?.firstName || "Student"} · ${record.competency || "Competency"}`, text: record.status || "Not Started", href: "competencies.html" })),
      ];
      queue.innerHTML = queueItems.length ? queueItems.map((item) => `<a href="${item.href}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 hover:border-emerald-300 dark:border-slate-700 dark:hover:border-emerald-700"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 ${item.color} dark:bg-slate-800"><i data-lucide="${item.icon}" class="h-4 w-4"></i></span><span class="min-w-0 flex-1"><b class="block truncate text-sm">${esc(item.label)}</b><small class="text-xs text-slate-400">${esc(item.text)}</small></span><i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i></a>`).join("") : `<div class="teacher-empty-state"><i data-lucide="check-check"></i><b>Your review queue is clear</b><p>There are no blank grades or competency actions waiting for you.</p></div>`;
      lucide.createIcons();
    }
  }

  function configureToolbar(options, onChange) {
    const filter = $("teacherFilter");
    if (!filter) return;
    filter.replaceChildren();
    options.forEach((optionData) => {
      const option = document.createElement("option");
      option.value = optionData.value ?? optionData;
      option.textContent = optionData.label ?? optionData;
      filter.append(option);
    });
    filter.addEventListener("change", onChange);
  }

  function injectSearch(placeholder, onInput) {
    const main = document.querySelector("main .max-w-7xl") || document.querySelector("main");
    if (!main || $("teacherToolbar")) return;
    const box = document.createElement("section");
    box.id = "teacherToolbar";
    box.className = "teacher-toolbar card p-4 sm:p-5";
    box.innerHTML = `<div class="flex flex-col gap-3 lg:flex-row lg:items-center"><label class="relative min-w-0 flex-1"><span class="sr-only">Search</span><i data-lucide="search" class="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-slate-400"></i><input id="teacherSearch" class="input w-full rounded-xl border py-3 pl-10 pr-4" placeholder="${esc(placeholder)}" /></label><select id="teacherFilter" class="input rounded-xl border px-4 py-3 lg:w-56" aria-label="Filter records"></select></div><div class="mt-3 flex flex-wrap items-center justify-between gap-3"><span id="teacherResultCount" class="text-xs font-medium text-slate-400"></span><div class="flex flex-wrap gap-2"><button id="teacherExport" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3.5 py-2 text-sm font-semibold hover:border-slate-300 dark:border-slate-700"><i data-lucide="download" class="h-4 w-4"></i>Export CSV</button><button id="teacherBulk" type="button" class="hidden inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><i data-lucide="layers-3" class="h-4 w-4"></i>Update visible</button></div></div>`;
    main.querySelector(".mb-7")?.after(box);
    $("teacherSearch")?.addEventListener("input", onInput);
    $("teacherExport")?.addEventListener("click", exportVisible);
    $("teacherBulk")?.addEventListener("click", bulkCompetencies);
    lucide.createIcons();
  }

  function renderStudents() {
    const data = assignedData();
    const query = ($( "teacherSearch")?.value || "").trim().toLowerCase();
    const filter = $("teacherFilter")?.value || "all";
    const students = assignedStudents(data).filter((student) => {
      const enrollment = latestEnrollment(student.id, data.enrollments);
      const haystack = `${fullName(student)} ${student.id} ${student.strand || student.track || ""}`.toLowerCase();
      const matchesQuery = !query || haystack.includes(query);
      const matchesFilter =
        filter === "all" ||
        (filter === "enrolled" && enrollment?.status) ||
        (filter === "needs-enrollment" && !enrollment);
      return matchesQuery && matchesFilter;
    });
    const table = $("rows");
    setText("teacherResultCount", `${students.length} of ${assignedStudents(data).length} students`);
    setText("studentTotal", assignedStudents(data).length);
    setText("studentEnrolled", assignedStudents(data).filter((student) => latestEnrollment(student.id, data.enrollments)?.status).length);
    setText("studentAttention", assignedStudents(data).filter((student) => !latestEnrollment(student.id, data.enrollments)).length);
    if (!students.length) {
      emptyRows(table, "No matching students", query || filter !== "all" ? "Try another search or filter." : "Assigned students will appear here.", 6);
      return;
    }
    table.innerHTML = students.map((student) => {
      const enrollment = latestEnrollment(student.id, data.enrollments);
      const grades = data.grades.filter((record) => record.studentId === student.id);
      const competencies = data.competencies.filter((record) => record.studentId === student.id);
      return `<tr class="teacher-table-row border-t border-slate-100 dark:border-slate-800"><td class="p-4"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-100 to-blue-100 text-xs font-extrabold text-emerald-700 dark:from-emerald-950 dark:to-blue-950 dark:text-emerald-300">${esc(`${student.firstName?.[0] || ""}${student.lastName?.[0] || ""}`.toUpperCase() || "ST")}</span><div><p class="font-semibold">${esc(fullName(student))}</p><p class="text-xs text-slate-400">${esc(student.email || "No email")}</p></div></div></td><td class="p-4 font-mono text-xs text-slate-500">${esc(student.id)}</td><td class="p-4">${esc(student.strand || student.track || "—")}</td><td class="p-4">${APP.statusBadge(enrollment?.status || "No enrollment")}</td><td class="p-4"><div class="flex flex-wrap gap-1.5 text-xs"><span class="rounded-full bg-blue-50 px-2 py-1 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300">${grades.length} grades</span><span class="rounded-full bg-violet-50 px-2 py-1 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">${competencies.length} competencies</span></div></td><td class="p-4 text-right"><button data-student="${esc(student.id)}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600 dark:bg-slate-100 dark:text-slate-900"><i data-lucide="eye" class="h-3.5 w-3.5"></i>View details</button></td></tr>`;
    }).join("");
    table.querySelectorAll("[data-student]").forEach((button) =>
      button.addEventListener("click", () => studentModal(button.dataset.student)),
    );
    lucide.createIcons();
  }

  function openNewGrade() {
    const dialog = $("newGradeDialog");
    const select = $("newGradeStudent");
    if (!dialog || !select) return;
    const students = assignedStudents(assignedData());
    select.replaceChildren();
    students.forEach((student) => {
      const option = document.createElement("option");
      option.value = student.id;
      option.textContent = `${fullName(student)} · ${student.id}`;
      select.append(option);
    });
    if (!students.length) {
      APP.toast("You have no assigned students", "error");
      return;
    }
    $("newGradeForm")?.reset();
    $("newGradeYear").value = "";
    dialog.showModal();
    lucide.createIcons();
  }

  function saveNewGrade(event) {
    event.preventDefault();
    const studentId = $("newGradeStudent")?.value;
    const subject = $("newGradeSubject")?.value.trim();
    const term = $("newGradeTerm")?.value.trim();
    const value = Number($("newGradeValue")?.value);
    if (!studentId || !subject || !term || !Number.isFinite(value) || value < 0 || value > 100) {
      APP.toast("Complete the required fields and enter a grade from 0 to 100", "error");
      return;
    }
    const data = assignedData();
    const student = assignedStudents(data).find((item) => item.id === studentId);
    if (!student) {
      APP.toast("You can only add grades for assigned students", "error");
      return;
    }
    const now = new Date().toISOString();
    const record = {
      id: DG.generateId("GRD"),
      studentId,
      teacherId: U.id,
      teacher: fullName(U),
      subject,
      subjectCode: $("newGradeCode")?.value.trim() || "",
      grade: value,
      remarks: value >= 75 ? "Passed" : "Failed",
      term,
      period: term,
      schoolYear: $("newGradeYear")?.value.trim() || "",
      notes: $("newGradeNotes")?.value.trim() || "",
      published: false,
      createdAt: now,
      updatedAt: now,
      updatedBy: U.id,
    };
    const grades = get("grades", []);
    grades.push(record);
    save("grades", grades);
    audit("grade", record, "Grade created", `Created ${record.grade} / ${record.remarks}`);
    notify(studentId, "Grade added", `${subject} grade was added and is awaiting publication.`, "grade", record.id);
    $("newGradeDialog")?.close();
    APP.toast("Grade saved as unpublished");
    renderGrades();
  }

  function visibleGrades() {
    const data = assignedData();
    const query = ($( "teacherSearch")?.value || "").trim().toLowerCase();
    const filter = $("teacherFilter")?.value || "all";
    return data.grades.filter((record) => {
      const student = studentFor(record.studentId, data.users);
      const haystack = `${fullName(student)} ${record.studentId} ${record.subject || ""} ${record.term || record.period || ""}`.toLowerCase();
      return data.ids.has(record.studentId) && (!query || haystack.includes(query)) && (filter === "all" || (record.published ? "published" : "unpublished") === filter);
    });
  }

  function renderGrades() {
    const data = assignedData();
    const list = visibleGrades();
    const numeric = list.filter((record) => Number.isFinite(Number(record.grade)));
    const average = numeric.length
      ? (numeric.reduce((sum, record) => sum + Number(record.grade), 0) / numeric.length).toFixed(2)
      : "—";
    renderMetricCards("gradeSummary", [
      { label: "Visible records", value: list.length, note: "Current filter", icon: "rows-3", iconClass: "bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300" },
      { label: "Needs grade", value: list.filter((record) => record.grade === null || record.grade === undefined || record.grade === "").length, note: "Blank values", icon: "circle-alert", iconClass: "bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300" },
      { label: "Class average", value: average, note: "Visible numeric grades", icon: "chart-no-axes-combined", iconClass: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300" },
      { label: "Published", value: list.filter((record) => record.published).length, note: "Student-visible", icon: "send", iconClass: "bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300" },
    ]);
    setText("teacherResultCount", `${list.length} grade record${list.length === 1 ? "" : "s"}`);
    const tbody = $("rows");
    if (!list.length) {
      emptyRows(tbody, "No grade records found", "Assigned grade records will appear here when available.", 8);
      return;
    }
    tbody.innerHTML = list.map((record) => {
      const student = studentFor(record.studentId, data.users);
      return `<tr class="teacher-table-row border-t border-slate-100 dark:border-slate-800"><td class="p-4"><p class="font-semibold">${esc(fullName(student) || record.studentId)}</p><p class="text-xs text-slate-400">${esc(record.studentId)}</p></td><td class="p-4"><p class="font-semibold">${esc(record.subject || "—")}</p><p class="text-xs text-slate-400">${esc(record.subjectCode || "No subject code")}</p></td><td class="p-4"><input data-grade="${esc(record.id)}" value="${esc(record.grade ?? "")}" type="number" min="0" max="100" step="0.01" class="teacher-inline-input input w-24 rounded-xl border px-3 py-2 font-semibold" aria-label="Grade for ${esc(record.subject || "subject")}" /></td><td class="p-4"><span data-grade-preview="${esc(record.id)}" class="text-sm font-semibold ${record.grade === null || record.grade === undefined || record.grade === "" ? "text-slate-400" : Number(record.grade) >= 75 ? "text-emerald-600" : "text-rose-600"}">${record.grade === null || record.grade === undefined || record.grade === "" ? "Pending" : Number(record.grade) >= 75 ? "Passed" : "Needs support"}</span></td><td class="p-4"><input data-term="${esc(record.id)}" value="${esc(record.term || record.period || "")}" placeholder="Term" class="teacher-inline-input input w-28 rounded-xl border px-3 py-2" aria-label="Term" /></td><td class="p-4"><select data-published="${esc(record.id)}" class="teacher-inline-input input rounded-xl border px-3 py-2" aria-label="Publication state"><option value="false" ${!record.published ? "selected" : ""}>Unpublished</option><option value="true" ${record.published ? "selected" : ""}>Published</option></select></td><td class="p-4">${publicationBadge(record.published)}</td><td class="p-4 text-right"><button data-save-grade="${esc(record.id)}" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"><i data-lucide="save" class="h-3.5 w-3.5"></i>Save</button><p class="mt-1 text-[11px] text-slate-400">${esc(APP.formatDate(record.updatedAt))}</p></td></tr>`;
    }).join("");
    tbody.querySelectorAll("[data-grade]").forEach((input) =>
      input.addEventListener("input", () => {
        const value = input.value === "" ? null : Number(input.value);
        const preview = tbody.querySelector(`[data-grade-preview="${CSS.escape(input.dataset.grade)}"]`);
        if (!preview) return;
        preview.textContent = value === null ? "Pending" : Number.isFinite(value) && value >= 75 ? "Passed" : "Needs support";
      }),
    );
    tbody.querySelectorAll("[data-save-grade]").forEach((button) =>
      button.addEventListener("click", () => saveGrade(button.dataset.saveGrade)),
    );
    lucide.createIcons();
  }

  function saveGrade(id) {
    const all = get("grades", []);
    const record = all.find((item) => item.id === id);
    const input = document.querySelector(`[data-grade="${CSS.escape(id)}"]`);
    if (!record || !input) return;
    const value = input.value === "" ? null : Number(input.value);
    if (value !== null && (!Number.isFinite(value) || value < 0 || value > 100)) {
      APP.toast("Grade must be between 0 and 100", "error");
      input.focus();
      return;
    }
    const term = document.querySelector(`[data-term="${CSS.escape(id)}"]`);
    const published = document.querySelector(`[data-published="${CSS.escape(id)}"]`);
    record.grade = value;
    record.remarks = value === null ? "Pending" : value >= 75 ? "Passed" : "Failed";
    record.term = term?.value.trim() || "";
    record.period = record.term;
    record.published = published?.value === "true";
    record.updatedAt = new Date().toISOString();
    record.updatedBy = U.id;
    save("grades", all);
    audit("grade", record, "Grade updated", `Saved ${record.grade ?? "blank"} / ${record.remarks}`);
    notify(record.studentId, record.published ? "Grade published" : "Grade updated", `${record.subject || "A subject"} grade was ${record.published ? "published" : "updated"}.`, "grade", record.id);
    APP.toast(record.published ? "Grade saved and published" : "Grade saved");
    renderGrades();
  }

  function visibleCompetencies() {
    const data = assignedData();
    const query = ($( "teacherSearch")?.value || "").trim().toLowerCase();
    const filter = $("teacherFilter")?.value || "all";
    return data.competencies.filter((record) => {
      const student = studentFor(record.studentId, data.users);
      const haystack = `${fullName(student)} ${record.studentId} ${record.competency || ""} ${record.qualification || ""}`.toLowerCase();
      return data.ids.has(record.studentId) && (!query || haystack.includes(query)) && (filter === "all" || (record.status || "Not Started") === filter);
    });
  }

  function renderCompetencies() {
    const data = assignedData();
    const list = visibleCompetencies();
    const records = data.competencies.filter((record) => data.ids.has(record.studentId));
    renderMetricCards("competencySummary", [
      { label: "Visible records", value: list.length, note: "Current filter", icon: "rows-3", iconClass: "bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300" },
      { label: "Not started", value: records.filter((record) => !record.status || record.status === "Not Started").length, note: "Ready to assess", icon: "circle-dashed", iconClass: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" },
      { label: "In progress", value: records.filter((record) => record.status === "In Progress").length, note: "Currently active", icon: "loader-circle", iconClass: "bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300" },
      { label: "Competent", value: records.filter((record) => record.status === "Competent").length, note: "Completed outcomes", icon: "badge-check", iconClass: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300" },
    ]);
    setText("teacherResultCount", `${list.length} competency record${list.length === 1 ? "" : "s"}`);
    const cards = $("cards");
    if (!cards) return;
    if (!list.length) {
      cards.innerHTML = `<div class="teacher-empty-state"><i data-lucide="award"></i><b>No matching competency records</b><p>Admin-created competency records for your assigned students will appear here.</p></div>`;
      lucide.createIcons();
      return;
    }
    cards.innerHTML = list.map((record) => {
      const student = studentFor(record.studentId, data.users);
      const status = record.status || "Not Started";
      return `<article class="teacher-competency-card card overflow-hidden"><div class="h-1.5 ${status === "Competent" ? "bg-emerald-500" : status === "In Progress" ? "bg-amber-500" : status === "Not Yet Competent" ? "bg-rose-500" : "bg-slate-300"}"></div><div class="p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400"><span>${esc(record.studentId)}</span><span class="h-1 w-1 rounded-full bg-slate-300"></span><span>${esc(record.qualification || "TVET")}</span></div><h3 class="mt-2 text-lg font-extrabold">${esc(record.competency || "Competency")}</h3><p class="mt-1 text-sm font-medium text-slate-500">${esc(fullName(student))}</p></div><div class="shrink-0">${statusBadge(status)}</div></div><div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="teacher-mini-detail"><span>Assessment date</span><b>${esc(APP.formatDate(record.assessmentDate))}</b></div><div class="teacher-mini-detail"><span>Assessor</span><b>${esc(record.assessor || "Not assigned")}</b></div><div class="teacher-mini-detail"><span>Evidence</span><b>${record.evidence ? '<span class="text-emerald-600">Attached</span>' : '<span class="text-slate-400">Not added</span>'}</b></div></div><p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">${esc(record.remarks || "Add assessment remarks and evidence when you review this competency.")}</p><div class="mt-5 flex flex-wrap items-center justify-between gap-3"><span class="text-xs text-slate-400">Last updated ${esc(APP.formatDate(record.updatedAt || record.createdAt))}</span><button data-edit-comp="${esc(record.id)}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"><i data-lucide="clipboard-pen-line" class="h-4 w-4"></i>Update assessment</button></div></div></article>`;
    }).join("");
    cards.querySelectorAll("[data-edit-comp]").forEach((button) =>
      button.addEventListener("click", () => editCompetency(button.dataset.editComp)),
    );
    lucide.createIcons();
  }

  function editCompetency(id) {
    const all = get("competencies", []);
    const record = all.find((item) => item.id === id);
    if (!record) return;
    const root = $("modalRoot");
    if (!root) return;
    root.innerHTML = `<div class="teacher-modal-backdrop fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4"><form id="compForm" class="teacher-modal-panel w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-600">Assessment workspace</p><h2 class="mt-1 text-2xl font-extrabold">Update competency</h2><p class="mt-1 text-sm text-slate-500">${esc(record.competency || "Competency")} · ${esc(record.studentId)}</p></div><button type="button" id="closeComp" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close"><i data-lucide="x"></i></button></div><div class="mt-6 grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Outcome status<select id="compStatus" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">${statuses.map((status) => `<option ${record.status === status ? "selected" : ""}>${status}</option>`).join("")}</select></label><label class="text-sm font-semibold">Assessment date<input id="compDate" type="date" value="${esc(record.assessmentDate ? String(record.assessmentDate).slice(0, 10) : new Date().toISOString().slice(0, 10))}" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label class="text-sm font-semibold sm:col-span-2">Assessor<input id="compAssessor" maxlength="120" value="${esc(record.assessor || fullName(U))}" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label class="text-sm font-semibold sm:col-span-2">Evidence link<input id="compEvidence" type="url" value="${esc(record.evidence || "")}" placeholder="https://..." class="input mt-1.5 w-full rounded-xl border px-3 py-2.5" /></label><label class="text-sm font-semibold sm:col-span-2">Assessment remarks<textarea id="compRemarks" rows="4" maxlength="500" class="input mt-1.5 w-full rounded-xl border px-3 py-2.5">${esc(record.remarks || "")}</textarea></label></div><div class="mt-6 flex justify-end gap-3"><button type="button" id="cancelComp" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">Cancel</button><button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"><i data-lucide="save" class="h-4 w-4"></i>Save assessment</button></div></form></div>`;
    const close = () => root.replaceChildren();
    $("closeComp")?.addEventListener("click", close);
    $("cancelComp")?.addEventListener("click", close);
    root.firstElementChild?.addEventListener("click", (event) => {
      if (event.target === root.firstElementChild) close();
    });
    $("compForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      const from = record.status || "Not Started";
      Object.assign(record, {
        status: $("compStatus").value,
        assessmentDate: $("compDate").value,
        assessor: $("compAssessor").value.trim(),
        evidence: $("compEvidence").value.trim(),
        remarks: $("compRemarks").value.trim(),
        updatedAt: new Date().toISOString(),
        updatedBy: U.id,
      });
      save("competencies", all);
      audit("competency", record, "Assessment updated", `${from} → ${record.status}`);
      notify(record.studentId, `Competency ${record.status}`, `${record.competency || "Competency"} was updated to ${record.status}.`, "competency", record.id);
      close();
      APP.toast("Competency assessment saved");
      renderCompetencies();
    });
    lucide.createIcons();
  }

  function bulkCompetencies() {
    const list = visibleCompetencies();
    if (!list.length) {
      APP.toast("There are no visible competency records to update", "error");
      return;
    }
    const status = window.prompt(`Set visible records to: ${statuses.join(", ")}`, "In Progress");
    if (!statuses.includes(status)) return;
    if (!window.confirm(`Update ${list.length} visible competency record${list.length === 1 ? "" : "s"} to ${status}?`)) return;
    const all = get("competencies", []);
    list.forEach((item) => {
      const record = all.find((candidate) => candidate.id === item.id);
      if (!record) return;
      record.status = status;
      record.assessmentDate = new Date().toISOString().slice(0, 10);
      record.assessor = record.assessor || fullName(U);
      record.updatedAt = new Date().toISOString();
      record.updatedBy = U.id;
      audit("competency", record, "Bulk assessment update", `Status set to ${status}`);
      notify(record.studentId, `Competency ${status}`, `${record.competency || "Competency"} was updated to ${status}.`, "competency", record.id);
    });
    save("competencies", all);
    APP.toast(`${list.length} assessment${list.length === 1 ? "" : "s"} updated`);
    renderCompetencies();
  }

  function downloadCsv(name, rows) {
    const csv = rows
      .map((row) => row.map((value) => `"${String(value ?? "").replaceAll('"', '""')}"`).join(","))
      .join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    link.download = name;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  function exportVisible() {
    const data = assignedData();
    if (page() === "grades.html") {
      downloadCsv("teacher-grades.csv", [["Student", "Student ID", "Subject", "Grade", "Remarks", "Term", "Publication"], ...visibleGrades().map((record) => [fullName(studentFor(record.studentId, data.users)), record.studentId, record.subject, record.grade ?? "", record.remarks || "", record.term || record.period || "", record.published ? "Published" : "Unpublished"])]);
    } else if (page() === "competencies.html") {
      downloadCsv("teacher-competencies.csv", [["Student", "Student ID", "Competency", "Qualification", "Status", "Assessment Date", "Assessor", "Remarks", "Evidence"], ...visibleCompetencies().map((record) => [fullName(studentFor(record.studentId, data.users)), record.studentId, record.competency, record.qualification, record.status, record.assessmentDate, record.assessor, record.remarks, record.evidence])]);
    } else {
      downloadCsv("teacher-students.csv", [["Student", "Student ID", "Strand", "Enrollment"], ...assignedStudents(data).map((student) => [fullName(student), student.id, student.strand || student.track || "", latestEnrollment(student.id, data.enrollments)?.status || "No enrollment"])]);
    }
    APP.toast("CSV exported");
  }


  function profile() {
      const fields = {
          nm: fullName(U),
          id: U.id,
          dept: U.department || "Faculty",
          firstName: U.firstName || "",
          lastName: U.lastName || "",
          email: U.email || "",
          specialization: U.specialization || "",
          phone: U.phone || "",
      };
      Object.entries(fields).forEach(([id, value]) => {
          const element = $(id);
          if (!element) return;
          if ("value" in element) {
              element.value = value;
          } else {
              element.textContent = value;
          }
      });
      const photo = $("av");
      if (photo) {
          photo.src = DG.getProfilePhoto(U);
      }
      const photoInput = $("profilePhotoInput");
      if (photoInput) {
          photoInput.addEventListener("change", () => {
              const file = photoInput.files?.[0];
              if (!file) return;
              if (file.size > 2 * 1024 * 1024) {
                  APP.toast(
                      "Profile photo must be smaller than 2 MB",
                      "error"
                  );
                  photoInput.value = "";
                  return;
              }
              const reader = new FileReader();
              reader.onload = () => {
                  DG.setProfilePhoto(reader.result, U);
                  if (photo) {
                      photo.src = reader.result;
                  }
                  document
                      .querySelectorAll("[data-profile-photo]")
                      .forEach(img => {
                          img.src = reader.result;
                      });
                  APP.toast("Profile photo updated");
              };
              reader.readAsDataURL(file);
          });
      }
      $("profileForm")?.addEventListener("submit", saveProfile);
      window.save = saveProfile;
  }
  function saveProfile(event) {
    event?.preventDefault();
    const users = get("users", []);
    const user = users.find((item) => item.id === U.id);
    if (!user) return;
    user.specialization = $("specialization")?.value.trim() || "";
    user.phone = $("phone")?.value.trim() || "";
    user.updatedAt = new Date().toISOString();
    save("users", users);
    Object.assign(U, user);
    DG.setCurrentUser(U);
    const button = $("saveProfile");
    if (button) {
      button.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i>Saved';
      setTimeout(() => {
        button.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i>Save changes';
        lucide.createIcons();
      }, 1800);
    }
    lucide.createIcons();
    APP.toast("Profile updated");
  }

  setup();
  if (page() === "dashboard.html") renderDashboard();
  if (page() === "students.html") {
    injectSearch("Search by student name, ID, strand, or track", renderStudents);
    configureToolbar(
      [
        { value: "all", label: "All students" },
        { value: "enrolled", label: "With enrollment" },
        { value: "needs-enrollment", label: "Needs enrollment" },
      ],
      renderStudents,
    );
    renderStudents();
  }
  if (page() === "grades.html") {
    $("newGrade")?.addEventListener("click", openNewGrade);
    $("closeNewGrade")?.addEventListener("click", () => $("newGradeDialog")?.close());
    $("cancelNewGrade")?.addEventListener("click", () => $("newGradeDialog")?.close());
    $("newGradeForm")?.addEventListener("submit", saveNewGrade);
    injectSearch("Search student, ID, subject, or term", renderGrades);
    configureToolbar(
      [
        { value: "all", label: "All publication states" },
        { value: "unpublished", label: "Unpublished" },
        { value: "published", label: "Published" },
      ],
      renderGrades,
    );
    renderGrades();
  }
  if (page() === "competencies.html") {
    injectSearch("Search student, ID, competency, or qualification", renderCompetencies);
    configureToolbar(
      [
        { value: "all", label: "All assessment states" },
        ...statuses.map((status) => ({ value: status, label: status })),
      ],
      renderCompetencies,
    );
    $("teacherBulk")?.classList.remove("hidden");
    renderCompetencies();
  }
  if (page() === "profile.html") profile();
})();
