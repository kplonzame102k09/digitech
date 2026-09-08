(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const statuses = [
    "Pending",
    "Processing",
    "Ready for Release",
    "Released",
    "Rejected",
  ];
  let admin;
  let users = [];
  let requests = [];
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
    "DG";
  const formatDate = (value) =>
    value
      ? new Date(value).toLocaleDateString(undefined, {
          month: "short",
          day: "numeric",
          year: "numeric",
        })
      : "Not recorded";
  const studentFor = (request) =>
    users.find((user) => user.id === request.studentId);
  const studentName = (request) =>
    studentFor(request)
      ? fullName(studentFor(request))
      : request.studentId || "Unknown student";
  const statusClass = (value) =>
    ({
      Pending:
        "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
      Processing:
        "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
      "Ready for Release":
        "bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300",
      Released:
        "bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300",
      Rejected: "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
    })[value] ||
    "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300";
  function badge(element, value) {
    if (!element) return;
    element.className = `inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ${statusClass(value)}`;
    element.textContent = value || "Not set";
  }
  function addAudit(request, from, to, notes, reason) {
    const logs = get("auditLogs");
    logs.push({
      id: DG.generateId("AUD"),
      entity: "document",
      recordId: request.id,
      action: "Document decision",
      from,
      to,
      notes: notes || "",
      reason: reason || "",
      actorId: admin.id,
      date: new Date().toISOString(),
    });
    save("auditLogs", logs);
  }
  function notifyRequester(request, status, message) {
    const targets = users.filter(
      (user) =>
        user.id === request.studentId ||
        (user.role === "parent" &&
          [
            user.childId,
            ...(user.childIds || []),
            ...(user.children || []),
          ].some(
            (value) =>
              (typeof value === "object"
                ? value.id || value.studentId
                : value) === request.studentId,
          )),
    );
    const notifications = get("notifications");
    targets.forEach((user) =>
      notifications.push({
        id: DG.generateId("NOT"),
        userId: user.id,
        title: `Document ${status}`,
        message:
          message ||
          `Your ${request.documentType || "document"} request ${request.id} is now ${status}.`,
        date: new Date().toISOString(),
        read: false,
        source: "document",
        documentId: request.id,
      }),
    );
    save("notifications", notifications);
  }
  function populateFilters() {
    const values = [
      ...new Set(
        requests.map((request) => request.documentType).filter(Boolean),
      ),
    ].sort();
    const select = $("#typeFilter");
    if (!select) return;
    const old = select.value;
    select.replaceChildren();
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All document types";
    select.append(all);
    values.forEach((value) => {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value;
      select.append(option);
    });
    select.value = old || "";
  }
  function visibleRequests() {
    const query = ($("#q")?.value || "").trim().toLowerCase();
    const status = $("#statusFilter")?.value || "";
    const type = $("#typeFilter")?.value || "";
    const sort = $("#sortBy")?.value || "date";
    return requests
      .filter((request) => {
        const haystack =
          `${studentName(request)} ${request.studentId || ""} ${request.id || ""} ${request.documentType || ""} ${request.purpose || ""}`.toLowerCase();
        return (
          (!query || haystack.includes(query)) &&
          (!status || request.status === status) &&
          (!type || request.documentType === type)
        );
      })
      .sort((a, b) => {
        const value = (request) =>
          sort === "student"
            ? studentName(request)
            : sort === "status"
              ? request.status
              : sort === "type"
                ? request.documentType
                : request.requestDate || request.updatedAt || "";
        return String(value(b)).localeCompare(String(value(a)));
      });
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

  function render() {
    populateFilters();
    const counts = requests.reduce((out, request) => {
      out[request.status] = (out[request.status] || 0) + 1;
      return out;
    }, {});
    setText("#totalCount", requests.length);
    setText("#pendingCount", counts.Pending || 0);
    setText("#processingCount", counts.Processing || 0);
    setText("#readyCount", counts["Ready for Release"] || 0);
    setText("#releasedCount", counts.Released || 0);
    const visible = visibleRequests();
    const container = $("#rows");
    container?.replaceChildren();
    setText("#resultCount", `${visible.length} of ${requests.length} requests`);
    setText(
      "#selectionCount",
      selected.size ? `${selected.size} selected` : "",
    );
    $("#emptyState")?.classList.toggle("hidden", visible.length > 0);
    visible.forEach((request) => {
      const row = clone("documentRowTemplate");
      if (!row) return;
      const checkbox = $("[data-select-record]", row);
      checkbox.checked = selected.has(request.id);
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) selected.add(request.id);
        else selected.delete(request.id);
        render();
      });
      const student = studentFor(request);
      setText("[data-student-initials]", initials(student), row);
      setStudentPhoto(row, student);
      setText("[data-student-name]", studentName(request), row);
      setText("[data-student-id]", request.studentId || "—", row);
      setText("[data-document-type]", request.documentType || "—", row);
      setText("[data-purpose]", request.purpose || "No purpose provided", row);
      setText("[data-request-date]", formatDate(request.requestDate), row);
      badge($("[data-record-status]", row), request.status);
      const inline = $("[data-inline-status]", row);
      if (inline) {
        inline.value = request.status || "Pending";
        inline.addEventListener("change", () =>
          updateRequest(request, inline.value, "Inline status update", ""),
        );
      }
      $$("[data-action]", row).forEach((button) =>
        button.addEventListener("click", () => {
          if (button.dataset.action === "review") openReview(request);
          if (button.dataset.action === "save")
            updateRequest(
              request,
              inline?.value || request.status,
              "Inline status update",
              "",
            );
        }),
      );
      container?.append(row);
    });
    const selectAll = $("#selectAll");
    if (selectAll) {
      const selectedVisible = visible.filter((request) =>
        selected.has(request.id),
      );
      selectAll.checked =
        visible.length > 0 && selectedVisible.length === visible.length;
      selectAll.indeterminate =
        selectedVisible.length > 0 && selectedVisible.length < visible.length;
    }
    $("[data-bulk-action]")?.classList.toggle("hidden", selected.size === 0);
    renderAudit();
    lucide.createIcons();
  }
  function detailGrid(request) {
    const student = studentFor(request);
    return [
      ["Request ID", request.id],
      ["Student", studentName(request)],
      ["Student ID", request.studentId],
      ["Email", student?.email || "—"],
      ["Document type", request.documentType || "—"],
      ["Purpose", request.purpose || "—"],
      ["Copies", request.copies || "1"],
      ["Requested", formatDate(request.requestDate)],
      ["Notes", request.notes || "—"],
      ["Last updated", formatDate(request.updatedAt)],
    ];
  }
  function openReview(request) {
    const dialog = $("#reviewDialog");
    setText("#reviewTitle", request.documentType || "Document request");
    setText(
      "#reviewSubtitle",
      `${request.id} · ${studentName(request)} · ${request.studentId}`,
    );
    const details = $("#reviewDetails");
    details?.replaceChildren();
    detailGrid(request).forEach(([label, value]) => {
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
    $("#reviewId").value = request.id;
    $("#reviewStatus").value = request.status || "Pending";
    $("#reviewNotes").value = request.reviewNotes || "";
    $("#reason").value = request.rejectionReason || "";
    $("#releaseMethod").value = request.releaseMethod || "";
    toggleReason();
    dialog?.showModal();
    lucide.createIcons();
  }
  function toggleReason() {
    const rejected = $("#reviewStatus")?.value === "Rejected";
    $("#reasonField")?.classList.toggle("hidden", !rejected);
    $("#reason")?.toggleAttribute("required", rejected);
  }
  function updateRequest(request, status, notes, reason, extra = {}) {
    if (!statuses.includes(status)) return false;
    if (status === "Rejected" && !reason.trim()) {
      APP.toast("Add a rejection reason before saving", "error");
      openReview(request);
      return false;
    }
    const from = request.status || "Pending";
    Object.assign(request, {
      status,
      reviewNotes: notes || "",
      rejectionReason: reason || "",
      reviewedAt: new Date().toISOString(),
      reviewedBy: admin.id,
      updatedAt: new Date().toISOString(),
      ...extra,
    });
    if (status === "Released") request.releaseDate = new Date().toISOString();
    save("documentRequests", requests);
    addAudit(request, from, status, notes, reason);
    notifyRequester(request, status, reason || notes);
    APP.toast(`Document marked ${status}`);
    render();
    return true;
  }
  function saveReview(event) {
    event.preventDefault();
    const request = requests.find((item) => item.id === $("#reviewId")?.value);
    if (!request) return;
    const ok = updateRequest(
      request,
      $("#reviewStatus").value,
      $("#reviewNotes").value.trim(),
      $("#reason").value.trim(),
      { releaseMethod: $("#releaseMethod").value },
    );
    if (ok) $("#reviewDialog")?.close();
  }
  function populateStudents() {
    const select = $("#createStudent");
    if (!select) return;
    select.replaceChildren();
    const empty = document.createElement("option");
    empty.value = "";
    empty.textContent = "Choose a student";
    select.append(empty);
    users
      .filter((user) => user.role === "student")
      .forEach((user) => {
        const option = document.createElement("option");
        option.value = user.id;
        option.textContent = `${fullName(user)} · ${user.id}`;
        select.append(option);
      });
  }
  function createRequest(event) {
    event.preventDefault();
    const studentId = $("#createStudent").value;
    if (!studentId) return;
    const request = {
      id: DG.generateId("DOC"),
      studentId,
      documentType: $("#createType").value.trim(),
      purpose: $("#createPurpose").value.trim(),
      copies: Number($("#createCopies").value) || 1,
      notes: $("#createNotes").value.trim(),
      requestDate: new Date().toISOString(),
      status: "Pending",
      createdBy: admin.id,
      updatedAt: new Date().toISOString(),
    };
    requests.push(request);
    save("documentRequests", requests);
    addAudit(
      request,
      "Not created",
      "Pending",
      "Request created by administrator",
      "",
    );
    notifyRequester(
      request,
      "Pending",
      "An administrator created a document request for your account.",
    );
    $("#createDialog")?.close();
    event.currentTarget.reset();
    APP.toast("Document request created");
    render();
  }
  function bulkAction() {
    if (!selected.size) return;
    const status = window.prompt(
      `Type one status: ${statuses.join(", ")}`,
      "Processing",
    );
    if (!statuses.includes(status)) return;
    let updated = 0;
    [...selected].forEach((id) => {
      const request = requests.find((item) => item.id === id);
      if (request && updateRequest(request, status, "Bulk status update", ""))
        updated += 1;
    });
    selected.clear();
    APP.toast(`${updated} request${updated === 1 ? "" : "s"} updated`);
    render();
  }
  function exportDocuments() {
    const rows = [
      [
        "Request ID",
        "Student",
        "Student ID",
        "Document",
        "Purpose",
        "Copies",
        "Requested",
        "Status",
        "Release method",
      ],
      ...visibleRequests().map((request) => [
        request.id,
        studentName(request),
        request.studentId,
        request.documentType || "",
        request.purpose || "",
        request.copies || 1,
        request.requestDate || "",
        request.status || "",
        request.releaseMethod || "",
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
    link.download = "digitech-document-requests.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("Document CSV downloaded");
  }
  function renderAudit() {
    const container = $("#auditRows");
    container?.replaceChildren();
    const logs = get("auditLogs")
      .filter((log) => log.entity === "document")
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 8);
    $("#auditEmpty")?.classList.toggle("hidden", logs.length > 0);
    logs.forEach((log) => {
      const row = clone("auditRowTemplate");
      if (!row) return;
      const request = requests.find((item) => item.id === log.recordId);
      setText(
        "[data-audit-title]",
        `${request ? studentName(request) : log.recordId}: ${log.from} → ${log.to}`,
        row,
      );
      setText("[data-audit-date]", formatDate(log.date), row);
      setText(
        "[data-audit-meta]",
        `Request ${log.recordId} · Admin ${log.actorId}`,
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
  
  function init() {
    admin = AUTH.requireRole("admin");
    if (!admin) return;
    users = get("users");
    requests = get("documentRequests");
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
    ["#q", "#statusFilter", "#typeFilter", "#sortBy"].forEach((selector) =>
      $(selector)?.addEventListener(
        selector === "#q" ? "input" : "change",
        render,
      ),
    );
    $("#selectAll")?.addEventListener("change", (event) => {
      visibleRequests().forEach((request) => {
        if (event.target.checked) selected.add(request.id);
        else selected.delete(request.id);
      });
      render();
    });
    $("[data-bulk-action]")?.addEventListener("click", bulkAction);
    $("[data-export-documents]")?.addEventListener("click", exportDocuments);
    $("[data-new-document]")?.addEventListener("click", () => {
      populateStudents();
      $("#createDialog")?.showModal();
    });
    $("#createForm")?.addEventListener("submit", createRequest);
    $$("[data-close-create]").forEach((button) =>
      button.addEventListener("click", () => $("#createDialog")?.close()),
    );
    $("#reviewForm")?.addEventListener("submit", saveReview);
    $("#reviewStatus")?.addEventListener("change", toggleReason);
    $$("[data-close-review]").forEach((button) =>
      button.addEventListener("click", () => $("#reviewDialog")?.close()),
    );
    render();
  }
  window.ADMIN_DOCUMENTS = { init };
})();

ADMIN_DOCUMENTS.init();
