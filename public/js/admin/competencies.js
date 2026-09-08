(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const statuses = [
    "Not Started",
    "In Progress",
    "Competent",
    "Not Yet Competent",
  ];
  let admin;
  let users = [];
  let competencies = [];
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
    "Unknown student";
  const initials = (user) =>
    `${user?.firstName?.[0] || ""}${user?.lastName?.[0] || ""}`.toUpperCase() ||
    "ST";
  const studentFor = (record) =>
    users.find((user) => user.id === record.studentId);
  const studentName = (record) =>
    studentFor(record)
      ? fullName(studentFor(record))
      : record.studentId || "Unknown student";
  const statusClass = (value) =>
    value === "Competent"
      ? "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
      : value === "In Progress"
        ? "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
        : value === "Not Yet Competent"
          ? "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
          : "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300";
  function badge(element, value) {
    if (!element) return;
    element.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusClass(value)}`;
    element.textContent = value || "Not started";
  }
  function addAudit(record, action, notes) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "competency",
      recordId: record.id || `${record.studentId}-${record.competency}`,
      action,
      actorId: admin.id,
      notes: notes || "",
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }
  function notifyStudent(
    record,
    title = `Competency ${record.status}`,
    message = `${record.competency || "Your competency"} assessment for ${studentName(record)} was updated to ${record.status}.`,
  ) {
    const targets = users.filter(
      (user) =>
        user.id === record.studentId ||
        (user.role === "parent" &&
          [
            user.childId,
            ...(user.childIds || []),
            ...(user.children || []),
          ].some(
            (value) =>
              (typeof value === "object"
                ? value.id || value.studentId
                : value) === record.studentId,
          )),
    );
    const notifications = get("notifications");
    targets.forEach((user) =>
      notifications.push({
        id: DG.generateId("NOT"),
        userId: user.id,
        title,
        message,
        date: new Date().toISOString(),
        read: false,
        source: "competency",
        competencyId: record.id,
      }),
    );
    save("notifications", notifications);
  }
  function populateFilters() {
    const qualifications = [
      ...new Set(
        competencies.map((record) => record.qualification).filter(Boolean),
      ),
    ].sort();
    const select = $("#qualificationFilter");
    const old = select?.value;
    if (select) {
      select.replaceChildren();
      const all = document.createElement("option");
      all.value = "";
      all.textContent = "All qualifications";
      select.append(all);
      qualifications.forEach((value) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = value;
        select.append(option);
      });
      select.value = old || "";
    }
  }
  function visibleRecords() {
    const query = ($("#q")?.value || "").trim().toLowerCase();
    const status = $("#statusFilter")?.value || "";
    const qualification = $("#qualificationFilter")?.value || "";
    const sort = $("#sortBy")?.value || "student";
    return competencies
      .filter((record) => {
        const haystack =
          `${studentName(record)} ${record.studentId || ""} ${record.competency || ""} ${record.qualification || ""}`.toLowerCase();
        return (
          (!query || haystack.includes(query)) &&
          (!status || record.status === status) &&
          (!qualification || record.qualification === qualification)
        );
      })
      .sort((a, b) => {
        const value = (record) =>
          sort === "status"
            ? record.status
            : sort === "date"
              ? record.assessmentDate || ""
              : sort === "qualification"
                ? record.qualification
                : studentName(record);
        return String(value(a)).localeCompare(String(value(b)));
      });
  }
  function render() {
    populateFilters();
    const visible = visibleRecords();
    setText("#totalCount", visible.length);
    setText(
      "#competentCount",
      visible.filter((record) => record.status === "Competent").length,
    );
    setText(
      "#progressCount",
      visible.filter((record) => record.status === "In Progress").length,
    );
    setText(
      "#reassessmentCount",
      visible.filter((record) => record.status === "Not Yet Competent").length,
    );
    const container = $("#rows");
    container?.replaceChildren();
    setText(
      "#resultCount",
      `${visible.length} of ${competencies.length} records`,
    );
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", visible.length > 0);
    visible.forEach((record) => {
      const row = clone("competencyRowTemplate");
      if (!row) return;
      const checkbox = $("[data-select-record]", row);
      checkbox.checked = selected.has(record.id);
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selected.add(record.id);
        else selected.delete(record.id);
        render();
      });
      const student = studentFor(record);
      //setText("[data-student-initials]", initials(student), row);
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
      setText("[data-student-name]", studentName(record), row);
      setText("[data-student-id]", record.studentId || "—", row);
      setText("[data-competency]", record.competency || "—", row);
      setText("[data-qualification]", record.qualification || "—", row);
      setText(
        "[data-assessment]",
        record.assessmentDate
          ? new Date(record.assessmentDate).toLocaleDateString()
          : "Not assessed",
        row,
      );
      badge($("[data-record-status]", row), record.status);
      $("[data-action=edit]", row)?.addEventListener("click", () =>
        openEditor(record),
      );
      container?.append(row);
    });
    const selectAll = $("#selectAll");
    if (selectAll) {
      const picked = visible.filter((record) => selected.has(record.id));
      selectAll.checked =
        visible.length > 0 && picked.length === visible.length;
      selectAll.indeterminate =
        picked.length > 0 && picked.length < visible.length;
    }
    $("[data-bulk-action]")?.classList.toggle("hidden", selected.size === 0);
    renderAudit();
    lucide.createIcons();
  }
  function populateCreateStudents() {
    const select = $("#createStudentId");
    if (!select) return;
    const previous = select.value;
    select.replaceChildren();
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = users.some((user) => user.role === "student")
      ? "Select a student"
      : "No student accounts available";
    select.append(placeholder);
    users
      .filter((user) => user.role === "student")
      .sort((a, b) => fullName(a).localeCompare(fullName(b)))
      .forEach((user) => {
        const option = document.createElement("option");
        option.value = user.id;
        option.textContent = `${fullName(user)} · ${user.id}`;
        select.append(option);
      });
    select.value = previous;
    select.disabled = !users.some((user) => user.role === "student");
  }

  function openCreateDialog() {
    const form = $("#createCompetencyForm");
    if (!form) return;
    form.reset();
    populateCreateStudents();
    $("#createStatus").value = "Not Started";
    $("#createCompetencyDialog")?.showModal();
    lucide.createIcons();
  }

  function createCompetency(event) {
    event.preventDefault();
    const studentId = $("#createStudentId")?.value;
    const competency = $("#createCompetency")?.value.trim();
    const qualification = $("#createQualification")?.value.trim();
    if (!studentId || !competency) {
      APP.toast("Select a student and enter a competency", "error");
      return;
    }
    const duplicate = competencies.some(
      (record) =>
        record.studentId === studentId &&
        String(record.competency || "").trim().toLowerCase() === competency.toLowerCase() &&
        String(record.qualification || "").trim().toLowerCase() === qualification.toLowerCase(),
    );
    if (duplicate) {
      APP.toast("This competency already exists for the selected student", "error");
      return;
    }
    const now = new Date().toISOString();
    const record = {
      id: DG.generateId("CMP"),
      studentId,
      competency,
      qualification,
      status: $("#createStatus")?.value || "Not Started",
      assessmentDate: $("#createAssessmentDate")?.value || "",
      assessor: $("#createAssessor")?.value.trim() || "",
      evidence: $("#createEvidence")?.value.trim() || "",
      remarks: $("#createRemarks")?.value.trim() || "",
      createdAt: now,
      createdBy: admin.id,
      updatedAt: now,
      updatedBy: admin.id,
    };
    competencies.push(record);
    save("competencies", competencies);
    addAudit(record, "Competency record created", "Record assigned by administrator");
    notifyStudent(
      record,
      "New competency assigned",
      `${record.competency} was added to your competency records.`,
    );
    $("#createCompetencyDialog")?.close();
    APP.toast("Competency record created");
    render();
  }

  function openEditor(record) {
    $("#competencyId").value = record.id;
    setText(
      "#competencyTitle",
      `${studentName(record)} · ${record.competency || "Competency"}`,
    );
    const context = $("#competencyContext");
    context?.replaceChildren();
    [
      ["Student ID", record.studentId],
      ["Qualification", record.qualification || "—"],
      ["Competency", record.competency || "—"],
      ["Current status", record.status || "Not Started"],
    ].forEach(([label, value]) => {
      const box = document.createElement("div");
      box.className = "rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
      const key = document.createElement("p");
      key.className = "text-xs text-slate-400";
      key.textContent = label;
      const val = document.createElement("p");
      val.className = "mt-1 text-sm font-semibold";
      val.textContent = value;
      box.append(key, val);
      context?.append(box);
    });
    $("#competencyStatus").value = record.status || "Not Started";
    $("#assessmentDate").value = record.assessmentDate || "";
    $("#assessor").value = record.assessor || "";
    $("#evidence").value = record.evidence || "";
    $("#remarks").value = record.remarks || "";
    $("#competencyDialog")?.showModal();
  }
  function saveAssessment(event) {
    event.preventDefault();
    const record = competencies.find(
      (item) => item.id === $("#competencyId")?.value,
    );
    if (!record) return;
    const from = record.status || "Not Started";
    Object.assign(record, {
      status: $("#competencyStatus").value,
      assessmentDate: $("#assessmentDate").value,
      assessor: $("#assessor").value.trim(),
      evidence: $("#evidence").value.trim(),
      remarks: $("#remarks").value.trim(),
      updatedAt: new Date().toISOString(),
      updatedBy: admin.id,
    });
    save("competencies", competencies);
    addAudit(record, "Assessment updated", `${from} → ${record.status}`);
    notifyStudent(record);
    $("#competencyDialog")?.close();
    APP.toast("Competency assessment saved");
    render();
  }
  function bulkAction() {
    if (!selected.size) return;
    const status = window.prompt(
      `Type one status: ${statuses.join(", ")}`,
      "In Progress",
    );
    if (!statuses.includes(status)) return;
    let updated = 0;
    [...selected].forEach((id) => {
      const record = competencies.find((item) => item.id === id);
      if (!record) return;
      record.status = status;
      record.assessmentDate = new Date().toISOString().slice(0, 10);
      record.updatedAt = new Date().toISOString();
      record.updatedBy = admin.id;
      addAudit(record, "Bulk assessment update", `Status set to ${status}`);
      notifyStudent(record);
      updated += 1;
    });
    save("competencies", competencies);
    selected.clear();
    APP.toast(
      `${updated} competency record${updated === 1 ? "" : "s"} updated`,
    );
    render();
  }
  function exportRecords() {
    const rows = [
      [
        "Student",
        "Student ID",
        "Competency",
        "Qualification",
        "Status",
        "Assessment date",
        "Assessor",
        "Remarks",
        "Evidence",
      ],
      ...visibleRecords().map((record) => [
        studentName(record),
        record.studentId,
        record.competency || "",
        record.qualification || "",
        record.status || "",
        record.assessmentDate || "",
        record.assessor || "",
        record.remarks || "",
        record.evidence || "",
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
    link.download = "digitech-competencies.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("Competency CSV downloaded");
  }
  function renderAudit() {
    const container = $("#auditRows");
    container?.replaceChildren();
    const logs = get("auditLogs")
      .filter((log) => log.entity === "competency")
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 8);
    $("#auditEmpty")?.classList.toggle("hidden", logs.length > 0);
    logs.forEach((log) => {
      const row = clone("auditRowTemplate");
      if (!row) return;
      const record = competencies.find(
        (item) =>
          (item.id || `${item.studentId}-${item.competency}`) === log.recordId,
      );
      setText(
        "[data-audit-title]",
        `${record ? studentName(record) : log.recordId}: ${log.action}`,
        row,
      );
      setText(
        "[data-audit-date]",
        new Date(log.date).toLocaleDateString(),
        row,
      );
      setText(
        "[data-audit-meta]",
        `Competency ${record?.competency || "record"} · Admin ${log.actorId}`,
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
    competencies = get("competencies");
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
    ["#q", "#statusFilter", "#qualificationFilter", "#sortBy"].forEach(
      (selector) =>
        $(selector)?.addEventListener(
          selector === "#q" ? "input" : "change",
          render,
        ),
    );
    $("#selectAll")?.addEventListener("change", (event) => {
      visibleRecords().forEach((record) => {
        if (event.target.checked) selected.add(record.id);
        else selected.delete(record.id);
      });
      render();
    });
    $("[data-bulk-action]")?.addEventListener("click", bulkAction);
    $("[data-export-competencies]")?.addEventListener("click", exportRecords);
    $("[data-add-competency]")?.addEventListener("click", openCreateDialog);
    $("#createCompetencyForm")?.addEventListener("submit", createCompetency);
    $$('[data-close-create-competency]').forEach((button) =>
      button.addEventListener("click", () => $("#createCompetencyDialog")?.close()),
    );
    $("#competencyForm")?.addEventListener("submit", saveAssessment);
    $$("[data-close-competency]").forEach((button) =>
      button.addEventListener("click", () => $("#competencyDialog")?.close()),
    );
    render();
  }
  window.ADMIN_COMPETENCIES = { init };
})();

ADMIN_COMPETENCIES.init();
