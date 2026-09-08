(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  let admin;
  let users = [];
  let grades = [];
  let selected = new Set();
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const setText = (selector, value, root = document) => {
    const element = typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
  };
  const clone = (id) => {
    const template = document.getElementById(id);
    return template
      ? document.importNode(template.content, true).firstElementChild
      : null;
  };
  const fullName = (user) =>
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    user?.id ||
    "Unknown user";
  const initials = (user) =>
    `${user?.firstName?.[0] || ""}${user?.lastName?.[0] || ""}`.toUpperCase() ||
    "DG";
  const studentFor = (grade) =>
    users.find((user) => user.id === grade.studentId);
  const studentName = (grade) =>
    studentFor(grade)
      ? fullName(studentFor(grade))
      : grade.studentId || "Unknown student";
  const isPublished = (grade) =>
    grade.published === true ||
    grade.published === "true" ||
    grade.status === "Published";
  const numericGrade = (grade) =>
    grade.grade === null || grade.grade === "" || grade.grade === undefined
      ? null
      : Number(grade.grade);
  const remark = (grade) =>
    grade.remarks ||
    (numericGrade(grade) !== null && numericGrade(grade) >= 75
      ? "Passed"
      : "Failed");
  const statusClass = (value) =>
    value === "Passed" || value === "Published"
      ? "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
      : value === "Failed"
        ? "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
        : "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300";
  function badge(element, value) {
    if (!element) return;
    element.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusClass(value)}`;
    element.textContent = value;
  }
  function addAudit(grade, action, notes) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "grade",
      recordId: grade.id || `${grade.studentId}-${grade.subject}`,
      action,
      actorId: admin.id,
      notes: notes || "",
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }
  function notifyStudent(grade) {
    const targets = users.filter(
      (user) =>
        user.id === grade.studentId ||
        (user.role === "parent" &&
          [
            user.childId,
            ...(user.childIds || []),
            ...(user.children || []),
          ].some(
            (value) =>
              (typeof value === "object"
                ? value.id || value.studentId
                : value) === grade.studentId,
          )),
    );
    const notifications = get("notifications");
    targets.forEach((user) =>
      notifications.push({
        id: DG.generateId("NOT"),
        userId: user.id,
        title: `Grade ${isPublished(grade) ? "published" : "updated"}`,
        message: `${grade.subject || "A subject"} grade for ${studentName(grade)} is ${isPublished(grade) ? "now available" : "updated"}.`,
        date: new Date().toISOString(),
        read: false,
        source: "grade",
        gradeId: grade.id,
      }),
    );
    save("notifications", notifications);
  }
  function populateFilters() {
    const subjects = [
      ...new Set(grades.map((grade) => grade.subject).filter(Boolean)),
    ].sort();
    const select = $("#subjectFilter");
    const old = select?.value;
    if (select) {
      select.replaceChildren();
      const all = document.createElement("option");
      all.value = "";
      all.textContent = "All subjects";
      select.append(all);
      subjects.forEach((value) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = value;
        select.append(option);
      });
      select.value = old || "";
    }
  }
  function visibleGrades() {
    const query = ($("#q")?.value || "").trim().toLowerCase();
    const subject = $("#subjectFilter")?.value || "";
    const result = $("#remarkFilter")?.value || "";
    const publish = $("#publishFilter")?.value || "";
    const sort = $("#sortBy")?.value || "student";
    return grades
      .filter((grade) => {
        const teacher =
          grade.teacher ||
          fullName(users.find((user) => user.id === grade.teacherId));
        const haystack =
          `${studentName(grade)} ${grade.studentId || ""} ${grade.subject || ""} ${teacher}`.toLowerCase();
        return (
          (!query || haystack.includes(query)) &&
          (!subject || grade.subject === subject) &&
          (!result || remark(grade) === result) &&
          (!publish ||
            (publish === "published" && isPublished(grade)) ||
            (publish === "unpublished" && !isPublished(grade)))
        );
      })
      .sort((a, b) => {
        const value = (grade) =>
          sort === "grade"
            ? (numericGrade(grade) ?? -1)
            : sort === "subject"
              ? grade.subject
              : sort === "status"
                ? remark(grade)
                : studentName(grade);
        return String(value(a)).localeCompare(String(value(b)), undefined, {
          numeric: true,
        });
      });
  }
  function render() {
    populateFilters();
    const visible = visibleGrades();
    const numeric = visible
      .map(numericGrade)
      .filter((value) => value !== null && !Number.isNaN(value));
    const passed = visible.filter((grade) => remark(grade) === "Passed").length;
    setText("#totalCount", visible.length);
    setText(
      "#averageCount",
      numeric.length
        ? (
            numeric.reduce((sum, value) => sum + value, 0) / numeric.length
          ).toFixed(2)
        : "—",
    );
    setText("#passedCount", `${passed}/${visible.length || 0}`);
    setText(
      "#unpublishedCount",
      visible.filter((grade) => !isPublished(grade)).length,
    );
    const container = $("#rows");
    container?.replaceChildren();
    setText("#resultCount", `${visible.length} of ${grades.length} grades`);
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", visible.length > 0);
    visible.forEach((grade) => {
      const row = clone("gradeRowTemplate");
      if (!row) return;
      const checkbox = $("[data-select-grade]", row);
      checkbox.checked = selected.has(grade.id);
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selected.add(grade.id);
        else selected.delete(grade.id);
        render();
      });
      const student = studentFor(grade);
      // setText("[data-student-initials]", initials(student), row);
      const photo = $("[data-student-photo]", row);
      const initialsElement = $("[data-student-initials]", row);

      if (student && photo) {
          photo.src = student.photo || "../assets/images/16432.png";
          photo.alt = `${fullName(student)} profile photo`;

          photo.classList.remove("hidden");
          initialsElement?.classList.add("hidden");

          photo.onerror = () => {
              photo.classList.add("hidden");

              if (initialsElement) {
                  initialsElement.textContent = initials(student);
                  initialsElement.classList.remove("hidden");
                  initialsElement.classList.add("flex");
              }
          };
      }
      setText("[data-student-name]", studentName(grade), row);
      setText("[data-student-id]", grade.studentId || "—", row);
      setText("[data-subject]", grade.subject || "—", row);
      setText(
        "[data-teacher]",
        grade.teacher ||
          fullName(users.find((user) => user.id === grade.teacherId)) ||
          "—",
        row,
      );
      setText(
        "[data-grade]",
        numericGrade(grade) === null ? "—" : numericGrade(grade),
        row,
      );
      badge($("[data-remark]", row), remark(grade));
      badge(
        $("[data-publish]", row),
        isPublished(grade) ? "Published" : "Unpublished",
      );
      $("[data-action=edit]", row)?.addEventListener("click", () =>
        openEditor(grade),
      );
      container?.append(row);
    });
    const selectAll = $("#selectAll");
    if (selectAll) {
      const picked = visible.filter((grade) => selected.has(grade.id));
      selectAll.checked =
        visible.length > 0 && picked.length === visible.length;
      selectAll.indeterminate =
        picked.length > 0 && picked.length < visible.length;
    }
    $("[data-bulk-publish]")?.classList.toggle("hidden", selected.size === 0);
    renderAudit();
    lucide.createIcons();
  }
  function openEditor(grade) {
    $("#gradeId").value = grade.id;
    setText(
      "#gradeTitle",
      `${studentName(grade)} · ${grade.subject || "Grade"}`,
    );
    const context = $("#gradeContext");
    context?.replaceChildren();
    [
      ["Student ID", grade.studentId],
      ["Teacher", grade.teacher || "—"],
      ["Term", grade.term || grade.period || "—"],
      ["Current result", remark(grade)],
    ].forEach(([label, value]) => {
      const box = document.createElement("div");
      box.className = "rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
      const key = document.createElement("p");
      key.className = "text-xs text-slate-400";
      key.textContent = label;
      const val = document.createElement("p");
      val.className = "mt-1 text-sm font-semibold";
      val.textContent = value || "—";
      box.append(key, val);
      context?.append(box);
    });
    $("#gradeValue").value = numericGrade(grade) ?? "";
    $("#gradeRemark").value = remark(grade);
    $("#gradeTerm").value = grade.term || grade.period || "";
    $("#gradePublished").value = String(isPublished(grade));
    $("#gradeNotes").value = grade.notes || "";
    $("#gradeDialog")?.showModal();
  }
  function saveGrade(event) {
    event.preventDefault();
    const grade = grades.find((item) => item.id === $("#gradeId")?.value);
    if (!grade) return;
    const value = Number($("#gradeValue").value);
    if (Number.isNaN(value) || value < 0 || value > 100) {
      APP.toast("Grade must be a number from 0 to 100", "error");
      return;
    }
    const before = `${grade.grade}/${remark(grade)}/${isPublished(grade)}`;
    Object.assign(grade, {
      grade: value,
      remarks: $("#gradeRemark").value,
      term: $("#gradeTerm").value.trim(),
      period: $("#gradeTerm").value.trim(),
      published: $("#gradePublished").value === "true",
      notes: $("#gradeNotes").value.trim(),
      updatedAt: new Date().toISOString(),
      updatedBy: admin.id,
    });
    save("grades", grades);
    addAudit(
      grade,
      "Grade updated",
      `${before} → ${grade.grade}/${grade.remarks}/${grade.published}`,
    );
    notifyStudent(grade);
    $("#gradeDialog")?.close();
    APP.toast("Grade saved");
    render();
  }
  function bulkPublish() {
    if (!selected.size) return;
    let updated = 0;
    grades.forEach((grade) => {
      if (selected.has(grade.id) && !isPublished(grade)) {
        grade.published = true;
        grade.publishedAt = new Date().toISOString();
        grade.publishedBy = admin.id;
        addAudit(grade, "Grade published", "Bulk publication");
        notifyStudent(grade);
        updated += 1;
      }
    });
    save("grades", grades);
    selected.clear();
    APP.toast(`${updated} grade${updated === 1 ? "" : "s"} published`);
    render();
  }
  function exportGrades() {
    const rows = [
      [
        "Student",
        "Student ID",
        "Subject",
        "Teacher",
        "Grade",
        "Result",
        "Term",
        "Published",
      ],
      ...visibleGrades().map((grade) => [
        studentName(grade),
        grade.studentId,
        grade.subject || "",
        grade.teacher || "",
        numericGrade(grade) ?? "",
        remark(grade),
        grade.term || grade.period || "",
        isPublished(grade) ? "Yes" : "No",
      ]),
    ];
    const csv = rows
      .map((row) =>
        row
          .map((value) => `"${String(value).replaceAll('"', '""')}"`)
          .join(","),
      )
      .join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(
      new Blob([csv], { type: "text/csv;charset=utf-8" }),
    );
    link.download = "digitech-grades.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("Grade CSV downloaded");
  }
  function renderAudit() {
    const container = $("#auditRows");
    container?.replaceChildren();
    const logs = get("auditLogs")
      .filter((log) => log.entity === "grade")
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 8);
    $("#auditEmpty")?.classList.toggle("hidden", logs.length > 0);
    logs.forEach((log) => {
      const row = clone("auditRowTemplate");
      if (!row) return;
      const grade = grades.find(
        (item) =>
          (item.id || `${item.studentId}-${item.subject}`) === log.recordId,
      );
      setText(
        "[data-audit-title]",
        `${grade ? studentName(grade) : log.recordId}: ${log.action}`,
        row,
      );
      setText(
        "[data-audit-date]",
        new Date(log.date).toLocaleDateString(),
        row,
      );
      setText(
        "[data-audit-meta]",
        `Grade ${grade?.subject || "record"} · Admin ${log.actorId}`,
        row,
      );
      setText("[data-audit-notes]", log.notes || "No additional notes", row);
      container?.append(row);
    });
  }
  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    users = get("users");
    grades = get("grades");
    const avatar = $("#avatar");
    if (avatar) {
      avatar.src = getProfilePhoto(admin);
      avatar.alt = `${fullName(admin)} profile photo`;
    }
    APP.applyTheme();
    APP.updateNotif();
    $("#open")?.addEventListener("click", () =>
      $("#side")?.classList.toggle("-translate-x-full"),
    );
    $$("[data-notifications]").forEach((button) =>
      button.addEventListener("click", () => APP.showNotifications()),
    );
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    [
      "#q",
      "#subjectFilter",
      "#remarkFilter",
      "#publishFilter",
      "#sortBy",
    ].forEach((selector) =>
      $(selector)?.addEventListener(
        selector === "#q" ? "input" : "change",
        render,
      ),
    );
    $("#selectAll")?.addEventListener("change", (event) => {
      visibleGrades().forEach((grade) => {
        if (event.target.checked) selected.add(grade.id);
        else selected.delete(grade.id);
      });
      render();
    });
    $("[data-bulk-publish]")?.addEventListener("click", bulkPublish);
    $("[data-export-grades]")?.addEventListener("click", exportGrades);
    $("#gradeForm")?.addEventListener("submit", saveGrade);
    $$("[data-close-grade]").forEach((button) =>
      button.addEventListener("click", () => $("#gradeDialog")?.close()),
    );
    render();
  }
  window.ADMIN_GRADES = { init };
})();

ADMIN_GRADES.init();
