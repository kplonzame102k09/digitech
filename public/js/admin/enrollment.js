(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const statuses = [
    "Draft",
    "Submitted",
    "Under Review",
    "Approved",
    "Rejected",
    "Needs Correction",
    "Enrolled",
  ];
  let admin;
  let users = [];
  let enrollments = [];
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
  const formatDate = (value) =>
    value
      ? new Date(value).toLocaleDateString(undefined, {
          month: "short",
          day: "numeric",
          year: "numeric",
        })
      : "Not recorded";
  const studentFor = (record) =>
    users.find((user) => user.id === record.studentId);
  const studentName = (record) =>
    studentFor(record)
      ? fullName(studentFor(record))
      : record.fullName || record.studentId || "Unknown student";
  const program = (record) =>
    record.strand || record.programType || "Not specified";
  const statusClass = (value) =>
    ({
      Approved:
        "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300",
      Enrolled:
        "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300",
      Submitted: "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
      "Under Review":
        "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
      "Needs Correction":
        "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
      Rejected: "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
    })[value] ||
    "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300";
  const isComplete = (record) =>
    ["Approved", "Complete", "Completed", "Verified", "Submitted"].includes(
      record.status,
    );

  function setBadge(element, value) {
    if (!element) return;
    element.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusClass(value)}`;
    element.textContent = value || "Not set";
  }

 function setStudentPhoto(row, student) {
    const photo = $("[data-student-photo]", row);
    const initialsElement = $("[data-student-initials]", row);
    if (!photo) return;

    const defaultPhoto = "../assets/images/16432.png";
    const photoUrl = student?.photo || defaultPhoto;

    photo.src = photoUrl;
    photo.alt = `${fullName(student)} profile photo`;
    photo.classList.remove("hidden");
    initialsElement?.classList.add("hidden");
    photo.addEventListener("error", () => {
      if (photo.src.endsWith(defaultPhoto)) return;
      photo.src = defaultPhoto;
    }, { once: true });
  }

  function relatedRecords(record) {
    const documents = get("documentRequests").filter(
      (item) => item.studentId === record.studentId,
    );
    const requirements = get("requirements").filter(
      (item) => item.studentId === record.studentId,
    );
    return { documents, requirements };
  }

  function updateStats() {
    const counts = enrollments.reduce((result, record) => {
      result[record.status] = (result[record.status] || 0) + 1;
      return result;
    }, {});
    setText("#totalCount", enrollments.length);
    setText("#submittedCount", counts.Submitted || 0);
    setText("#reviewCount", counts["Under Review"] || 0);
    setText("#enrolledCount", counts.Enrolled || 0);
    setText(
      "#correctionCount",
      (counts["Needs Correction"] || 0) + (counts.Rejected || 0),
    );
  }

  function filterValues() {
    const programs = [
      ...new Set(
        enrollments.map(program).filter((value) => value !== "Not specified"),
      ),
    ].sort();
    const years = [
      ...new Set(
        enrollments.map((record) => record.schoolYear).filter(Boolean),
      ),
    ]
      .sort()
      .reverse();
    const programSelect = $("#programFilter");
    const yearSelect = $("#yearFilter");
    const oldProgram = programSelect?.value;
    const oldYear = yearSelect?.value;
    if (programSelect) {
      programSelect.replaceChildren();
      const all = document.createElement("option");
      all.value = "";
      all.textContent = "All programs";
      programSelect.append(all);
      programs.forEach((value) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = value;
        programSelect.append(option);
      });
      programSelect.value = oldProgram || "";
    }
    if (yearSelect) {
      yearSelect.replaceChildren();
      const all = document.createElement("option");
      all.value = "";
      all.textContent = "All school years";
      yearSelect.append(all);
      years.forEach((value) => {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = value;
        yearSelect.append(option);
      });
      yearSelect.value = oldYear || "";
    }
  }

  function visibleRecords() {
    const query = ($("#q")?.value || "").trim().toLowerCase();
    const status = $("#statusFilter")?.value || "";
    const selectedProgram = $("#programFilter")?.value || "";
    const year = $("#yearFilter")?.value || "";
    const sort = $("#sortBy")?.value || "updated";
    return enrollments
      .filter((record) => {
        const haystack =
          `${studentName(record)} ${record.studentId || ""} ${record.id || ""} ${program(record)} ${record.programType || ""}`.toLowerCase();
        return (
          (!query || haystack.includes(query)) &&
          (!status || record.status === status) &&
          (!selectedProgram || program(record) === selectedProgram) &&
          (!year || record.schoolYear === year)
        );
      })
      .sort((a, b) => {
        const value = (record) =>
          sort === "student"
            ? studentName(record)
            : sort === "status"
              ? record.status
              : sort === "schoolYear"
                ? record.schoolYear
                : record.updatedAt ||
                  record.submittedAt ||
                  record.createdAt ||
                  "";
        return String(value(b)).localeCompare(String(value(a)));
      });
  }

  function render() {
    filterValues();
    updateStats();
    const records = visibleRecords();
    const container = $("#rows");
    container?.replaceChildren();
    setText(
      "#resultCount",
      `${records.length} of ${enrollments.length} records`,
    );
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", records.length > 0);
    records.forEach((record) => {
      const row = clone("enrollmentRowTemplate");
      if (!row) return;
      const checkbox = $("[data-select-record]", row);
      checkbox.checked = selected.has(record.id);
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selected.add(record.id);
        else selected.delete(record.id);
        render();
      });        
      const student = studentFor(record);
      setText("[data-student-initials]", initials(student), row);
      setStudentPhoto(row, student);
      setText("[data-student-name]", studentName(record), row);
      setText("[data-student-id]", record.studentId || "—", row);
      setText("[data-program]", program(record), row);
      setText("[data-school-year]", record.schoolYear || "—", row);
      setBadge($("[data-record-status]", row), record.status);
      const inline = $("[data-inline-status]", row);
      if (inline) {
        inline.value = record.status || "Draft";
        inline.addEventListener("change", () => {
          if (inline.value === record.status) return;
          updateRecord(record, inline.value, "Inline status update", "");
        });
      }
      $$("[data-action]", row).forEach((button) =>
        button.addEventListener("click", () => {
          if (button.dataset.action === "review") openReview(record);
          if (button.dataset.action === "save")
            updateRecord(
              record,
              inline?.value || record.status,
              "Inline status update",
              "",
            );
        }),
      );
      container?.append(row);
    });
    const visibleIds = new Set(records.map((record) => record.id));
    const selectAll = $("#selectAll");
    if (selectAll) {
      const visibleSelected = records.filter((record) =>
        selected.has(record.id),
      );
      selectAll.checked =
        records.length > 0 && visibleSelected.length === records.length;
      selectAll.indeterminate =
        visibleSelected.length > 0 && visibleSelected.length < records.length;
    }
    $("[data-bulk-action]")?.classList.toggle("hidden", selected.size === 0);
    lucide.createIcons();
    renderAudit();
  }

  function fillSelect(id, values, emptyLabel) {
    const select = $(id);
    if (!select) return;
    select.replaceChildren();
    const empty = document.createElement("option");
    empty.value = "";
    empty.textContent = emptyLabel;
    select.append(empty);
    values.forEach((item) => {
      const option = document.createElement("option");
      option.value = item.value;
      option.textContent = item.label;
      select.append(option);
    });
  }

  function detailGrid(record) {
    const student = studentFor(record);
    return [
      ["Record ID", record.id],
      ["Student", studentName(record)],
      ["Student ID", record.studentId],
      ["Email", student?.email || "—"],
      ["Contact", student?.contact || record.contact || "—"],
      ["Birth date", student?.birthDate || record.birthDate || "—"],
      ["Address", student?.address || record.address || "—"],
      ["Guardian", student?.guardianName || record.guardianName || "—"],
      [
        "Guardian contact",
        student?.guardianContact || record.guardianContact || "—",
      ],
      ["Program type", record.programType || "—"],
      ["Grade level", record.gradeLevel || "—"],
      ["Strand", record.strand || "—"],
      ["Track", record.track || "—"],
      ["Training level", record.trainingLevel || record.training || "—"],
      ["School year", record.schoolYear || "—"],
      ["Last updated", formatDate(record.updatedAt)],
    ];
  }

  function openReview(record) {
    const dialog = $("#reviewDialog");
    if (!dialog) return;
    setText("#reviewTitle", studentName(record));
    setText(
      "#reviewSubtitle",
      `${record.id} · ${record.studentId} · Current status: ${record.status || "Draft"}`,
    );
    const details = $("#reviewDetails");
    details?.replaceChildren();
    detailGrid(record).forEach(([label, value]) => {
      const box = document.createElement("div");
      box.className = "rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
      const key = document.createElement("p");
      key.className = "text-xs text-slate-400";
      key.textContent = label;
      const val = document.createElement("p");
      val.className = "mt-1 text-sm font-semibold";
      val.textContent = value || "—";
      box.append(key, val);
      details?.append(box);
    });
    const checks = $("#requirementChecks");
    checks?.replaceChildren();
    const related = relatedRecords(record);
    const linked = [
      ...related.documents.map((item) => ({
        type: "Document",
        label: item.type || item.name || item.id,
        status: item.status,
      })),
      ...related.requirements.map((item) => ({
        type: "Requirement",
        label: item.name || item.type || item.id,
        status: item.status,
      })),
    ];
    if (!linked.length) {
      const empty = document.createElement("p");
      empty.className =
        "rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-slate-800";
      empty.textContent =
        "No linked document or requirement records have been submitted.";
      checks?.append(empty);
    } else
      linked.forEach((item) => {
        const row = document.createElement("div");
        row.className =
          "flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800";
        const label = document.createElement("div");
        const name = document.createElement("b");
        name.className = "block text-sm";
        name.textContent = item.label;
        const type = document.createElement("small");
        type.className = "text-xs text-slate-400";
        type.textContent = item.type;
        label.append(name, type);
        const badge = document.createElement("span");
        setBadge(badge, item.status || "Not submitted");
        row.append(label, badge);
        checks?.append(row);
      });
    $("#reviewId").value = record.id;
    $("#reviewStatus").value = record.status || "Draft";
    $("#reviewNotes").value = record.reviewNotes || "";
    $("#rejectionReason").value = record.rejectionReason || "";
    $("#assignedSection").value = record.assignedSection || "";
    fillSelect(
      "#assignedTeacher",
      users
        .filter((user) => user.role === "teacher")
        .map((user) => ({
          value: user.id,
          label: `${fullName(user)} · ${user.id}`,
        })),
      "No adviser assigned",
    );
    $("#assignedTeacher").value = record.assignedTeacherId || "";
    toggleReasonField();
    $("#reviewFeedback").textContent = "";
    dialog.showModal();
    lucide.createIcons();
  }

  function toggleReasonField() {
    const needsReason = ["Rejected", "Needs Correction"].includes(
      $("#reviewStatus")?.value,
    );
    $("#rejectionReasonField")?.classList.toggle("hidden", !needsReason);
    $("#rejectionReason")?.toggleAttribute("required", needsReason);
  }

  function addAudit(record, from, to, notes, reason) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "enrollment",
      recordId: record.id,
      action: "Enrollment decision",
      from,
      to,
      notes: notes || "",
      reason: reason || "",
      actorId: admin.id,
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }

  function notifyStudent(record, status, reason) {
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
    APP.notifyUsers(
      targets.map((user) => user.id),
      `Enrollment ${status}`,
      reason || `Your enrollment record ${record.id} is now ${status}.`,
      "enrollment",
      record.id,
    );
  }

  function hasDuplicateActive(record, newStatus) {
    return (
      newStatus === "Enrolled" &&
      enrollments.some(
        (item) =>
          item.id !== record.id &&
          item.studentId === record.studentId &&
          ["Approved", "Enrolled"].includes(item.status) &&
          item.schoolYear === record.schoolYear,
      )
    );
  }

  function updateRecord(
    record,
    newStatus,
    notes = "",
    reason = "",
    extra = {},
  ) {
    if (!newStatus || !statuses.includes(newStatus)) return false;
    if (hasDuplicateActive(record, newStatus)) {
      APP.toast(
        "This student already has an active enrollment for that school year",
        "error",
      );
      render();
      return false;
    }
    if (
      ["Rejected", "Needs Correction"].includes(newStatus) &&
      !reason.trim()
    ) {
      APP.toast("Add a reason before returning this enrollment", "error");
      openReview(record);
      return false;
    }
    const from = record.status || "Draft";
    const previousReason = record.rejectionReason || "";
    Object.assign(record, {
      status: newStatus,
      reviewNotes: notes,
      rejectionReason: reason,
      reviewedAt: new Date().toISOString(),
      reviewedBy: admin.id,
      updatedAt: new Date().toISOString(),
      ...extra,
    });
    save("enrollments", enrollments);
    addAudit(record, from, newStatus, notes, reason);
    if (from !== newStatus || reason !== previousReason)
      notifyStudent(record, newStatus, reason || notes);
    APP.toast(`Enrollment marked ${newStatus}`);
    render();
    return true;
  }

  function saveReview(event) {
    event.preventDefault();
    const record = enrollments.find(
      (item) => item.id === $("#reviewId")?.value,
    );
    if (!record) return;
    const ok = updateRecord(
      record,
      $("#reviewStatus").value,
      $("#reviewNotes").value.trim(),
      $("#rejectionReason").value.trim(),
      {
        assignedTeacherId: $("#assignedTeacher").value,
        assignedSection: $("#assignedSection").value.trim(),
      },
    );
    if (ok) $("#reviewDialog")?.close();
  }

  function createEnrollment(event) {
    event.preventDefault();
    const studentId = $("#createStudent").value;
    const student = users.find((user) => user.id === studentId);
    if (!student) return;
    const schoolYear = $("#createYear").value.trim();
    const duplicate = enrollments.some(
      (record) =>
        record.studentId === studentId &&
        record.schoolYear === schoolYear &&
        ["Submitted", "Under Review", "Approved", "Enrolled"].includes(
          record.status,
        ),
    );
    if (duplicate) {
      setText(
        "#createFeedback",
        "This student already has an active record for that school year.",
      );
      return;
    }
    const record = {
      id: DG.generateId("ENR"),
      studentId,
      status: $("#createStatus").value,
      programType: $("#createProgram").value.trim(),
      gradeLevel: $("#createGrade").value.trim(),
      strand: $("#createStrand").value.trim(),
      track: $("#createTrack").value.trim(),
      schoolYear,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    enrollments.push(record);
    save("enrollments", enrollments);
    addAudit(
      record,
      "Not created",
      record.status,
      "Record created by administrator",
      "",
    );
    notifyStudent(
      record,
      record.status,
      "An administrator created an enrollment record for your account.",
    );
    $("#createDialog")?.close();
    APP.toast("Enrollment record created");
    event.currentTarget.reset();
    render();
  }

  function bulkAction() {
    if (!selected.size) return;
    const value = window.prompt(
      `Type one status: ${statuses.join(", ")}`,
      "Under Review",
    );
    if (!statuses.includes(value)) return;
    const reason = ["Rejected", "Needs Correction"].includes(value)
      ? window.prompt(
          `Enter a reason for ${value.toLowerCase()} (required):`,
          "",
        )?.trim() || ""
      : "";
    if (["Rejected", "Needs Correction"].includes(value) && !reason) {
      APP.toast("A reason is required for this bulk action", "error");
      return;
    }
    let updated = 0;
    selected.forEach((id) => {
      const record = enrollments.find((item) => item.id === id);
      if (record && updateRecord(record, value, "Bulk status update", reason))
        updated += 1;
    });
    selected.clear();
    APP.toast(
      `${updated} enrollment record${updated === 1 ? "" : "s"} updated`,
    );
    render();
  }

  function exportEnrollments() {
    const rows = [
      [
        "Record ID",
        "Student",
        "Student ID",
        "Program",
        "School Year",
        "Status",
        "Assigned Adviser",
        "Section",
      ],
      ...visibleRecords().map((record) => [
        record.id,
        studentName(record),
        record.studentId,
        program(record),
        record.schoolYear || "",
        record.status || "",
        fullName(users.find((user) => user.id === record.assignedTeacherId)),
        record.assignedSection || "",
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
    link.download = "digitech-enrollments.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("Enrollment CSV downloaded");
  }

  function renderAudit() {
    const container = $("#auditRows");
    container?.replaceChildren();
    const logs = get("auditLogs")
      .filter((item) => item.entity === "enrollment")
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 8);
    $("#auditEmpty")?.classList.toggle("hidden", logs.length > 0);
    logs.forEach((log) => {
      const row = clone("auditRowTemplate");
      if (!row) return;
      const record = enrollments.find((item) => item.id === log.recordId);
      setText(
        "[data-audit-title]",
        `${record ? studentName(record) : log.recordId}: ${log.from} → ${log.to}`,
        row,
      );
      setText("[data-audit-date]", formatDate(log.date), row);
      setText(
        "[data-audit-meta]",
        `Record ${log.recordId} · Admin ${log.actorId}`,
        row,
      );
      setText(
        "[data-audit-notes]",
        log.reason || log.notes || "No additional notes",
        row,
      );
      container?.append(row);
    });
  }

  function populateCreateStudents() {
    fillSelect(
      "#createStudent",
      users
        .filter(
          (user) => user.role === "student" && user.status !== "inactive",
        )
        .map((user) => ({
          value: user.id,
          label: `${fullName(user)} · ${user.id}`,
        })),
      "Choose a student",
    );
  }
  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    users = get("users");
    enrollments = get("enrollments");
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
    ["#q", "#statusFilter", "#programFilter", "#yearFilter", "#sortBy"].forEach(
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
    $("[data-export-enrollments]")?.addEventListener(
      "click",
      exportEnrollments,
    );
    $("[data-new-enrollment]")?.addEventListener("click", () => {
      populateCreateStudents();
      $("#createDialog")?.showModal();
    });
    $("#createForm")?.addEventListener("submit", createEnrollment);
    $$("[data-close-create]").forEach((button) =>
      button.addEventListener("click", () => $("#createDialog")?.close()),
    );
    $("#reviewForm")?.addEventListener("submit", saveReview);
    $("#reviewStatus")?.addEventListener("change", toggleReasonField);
    $$("[data-close-review]").forEach((button) =>
      button.addEventListener("click", () => $("#reviewDialog")?.close()),
    );
    render();
  }
  window.ADMIN_ENROLLMENT = { init };
})();

ADMIN_ENROLLMENT.init();
