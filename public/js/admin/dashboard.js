(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const text = (selector, value, root = document) => {
    const element = $(selector, root);
    if (element) element.textContent = value ?? "";
    return element;
  };
  const clear = (selector) => $(selector)?.replaceChildren();
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
  const formatDate = (value) =>
    value
      ? new Date(value).toLocaleDateString(undefined, {
          month: "short",
          day: "numeric",
          year: "numeric",
        })
      : "—";
  const status = (element, value) => {
    if (!element) return;
    element.textContent = value || "Not set";
    element.className =
      "inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300";
    if (["Approved", "Enrolled", "Completed", "Present"].includes(value))
      element.className =
        "inline-flex rounded-full bg-green-50 px-2.5 py-1 text-[11px] font-semibold text-green-700 dark:bg-green-950 dark:text-green-300";
    if (
      ["Pending", "Processing", "Submitted", "Under Review", "Late"].includes(
        value,
      )
    )
      element.className =
        "inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300";
    if (["Absent", "Rejected", "Failed"].includes(value))
      element.className =
        "inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300";
  };

  function attendanceChart(records) {
    const chart = $("#attendanceChart");
    if (!chart) return;
    chart.replaceChildren();
    const grouped = new Map();
    records.forEach((record) => {
      const key = record.date
        ? new Date(record.date).toISOString().slice(0, 10)
        : "Undated";
      const group = grouped.get(key) || { present: 0, total: 0 };
      group.total += 1;
      if (record.status === "Present") group.present += 1;
      grouped.set(key, group);
    });
    const points = [...grouped.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .slice(-7);
    if (!points.length) return;
    const coordinates = points.map(([date, value], index) => ({
      x: 20 + index * (460 / Math.max(points.length - 1, 1)),
      y: 112 - (value.present / Math.max(value.total, 1)) * 88,
      date,
      rate: Math.round((value.present / Math.max(value.total, 1)) * 100),
    }));
    const baseline = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "line",
    );
    baseline.setAttribute("x1", "20");
    baseline.setAttribute("x2", "480");
    baseline.setAttribute("y1", "112");
    baseline.setAttribute("y2", "112");
    baseline.setAttribute("stroke", "#cbd5e1");
    baseline.setAttribute("stroke-width", "1");
    chart.append(baseline);
    const path = document.createElementNS(
      "http://www.w3.org/2000/svg",
      "polyline",
    );
    path.setAttribute("fill", "none");
    path.setAttribute("stroke", "#16a34a");
    path.setAttribute("stroke-width", "3");
    path.setAttribute("stroke-linecap", "round");
    path.setAttribute("stroke-linejoin", "round");
    path.setAttribute(
      "points",
      coordinates.map((point) => `${point.x},${point.y}`).join(" "),
    );
    chart.append(path);
    coordinates.forEach((point) => {
      const circle = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "circle",
      );
      circle.setAttribute("cx", point.x);
      circle.setAttribute("cy", point.y);
      circle.setAttribute("r", "4");
      circle.setAttribute("fill", "#ffffff");
      circle.setAttribute("stroke", "#16a34a");
      circle.setAttribute("stroke-width", "3");
      circle.setAttribute(
        "aria-label",
        `${point.rate}% present on ${point.date}`,
      );
      chart.append(circle);
      const label = document.createElementNS(
        "http://www.w3.org/2000/svg",
        "text",
      );
      label.setAttribute("x", point.x);
      label.setAttribute("y", "128");
      label.setAttribute("text-anchor", "middle");
      label.setAttribute("font-size", "9");
      label.setAttribute("fill", "#94a3b8");
      label.textContent =
        point.date === "Undated"
          ? "—"
          : new Date(point.date).toLocaleDateString(undefined, {
              month: "short",
              day: "numeric",
            });
      chart.append(label);
    });
  }

  function renderAttendance(records) {
    const counts = records.reduce((result, record) => {
      const key = record.status || "Other";
      result[key] = (result[key] || 0) + 1;
      return result;
    }, {});
    const present = counts.Present || 0;
    const late = counts.Late || 0;
    const absent = counts.Absent || 0;
    const total = present + late + absent;
    text("#attendancePresent", present);
    text("#attendanceLate", late);
    text("#attendanceAbsent", absent);
    text(
      "#attendanceRate",
      `${total ? Math.round((present / total) * 100) : 0}%`,
    );
    const bars = $("#attendanceBars");
    bars?.replaceChildren();
    const grouped = new Map();
    records.forEach((record) => {
      const key = record.date
        ? new Date(record.date).toISOString().slice(0, 10)
        : "Undated";
      const item = grouped.get(key) || { present: 0, total: 0 };
      item.total += 1;
      if (record.status === "Present") item.present += 1;
      grouped.set(key, item);
    });
    [...grouped.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .slice(-7)
      .forEach(([date, item]) => {
        const wrapper = document.createElement("div");
        wrapper.className = "flex min-w-0 flex-1 flex-col items-center gap-2";
        const bar = document.createElement("div");
        bar.className = "w-full max-w-8 rounded-t-lg bg-green-500/80";
        bar.style.height = `${Math.max(8, Math.round((item.present / Math.max(item.total, 1)) * 105))}px`;
        bar.title = `${item.present} present of ${item.total}`;
        const label = document.createElement("span");
        label.className = "truncate text-[10px] text-slate-400";
        label.textContent =
          date === "Undated"
            ? "—"
            : new Date(date).toLocaleDateString(undefined, {
                weekday: "short",
              });
        wrapper.append(bar, label);
        bars?.append(wrapper);
      });
    setVisibility("#attendanceEmpty", !records.length);
    attendanceChart(records);
  }

  function setVisibility(selector, visible) {
    $(selector)?.classList.toggle("hidden", !visible);
  }

  function renderQueue(selector, emptySelector, records, users, link) {
    const container = $(selector);
    container?.replaceChildren();
    setVisibility(emptySelector, !records.length);
    records.slice(0, 6).forEach((record) => {
      const row = clone("queueItemTemplate");
      if (!row) return;
      const user = users.find((item) => item.id === record.studentId);
      text("[data-queue-title]", user ? fullName(user) : record.studentId, row);
      text(
        "[data-queue-meta]",
        `${record.id || "Record"} · ${formatDate(record.submittedAt || record.requestDate || record.updatedAt || record.date)}`,
        row,
      );
      status($("[data-queue-status]", row), record.status);
      const action = $("[data-queue-link]", row);
      if (action) {
        action.href = `${link}?id=${encodeURIComponent(record.id || record.studentId || "")}`;
      }
      container?.append(row);
    });
  }

  function renderStats(
    users,
    enrollments,
    documents,
    grades,
    competencies,
    notifications,
    attendance,
  ) {
    const stats = [
      ["Users", users.length],
      ["Enrollments", enrollments.length],
      ["Document requests", documents.length],
      ["Grades", grades.length],
      ["Competency records", competencies.length],
      ["Attendance records", attendance.length],
      ["Notifications", notifications.length],
    ];
    const container = $("#stats");
    container?.replaceChildren();
    stats.forEach(([label, value]) => {
      const row = clone("statRowTemplate");
      if (!row) return;
      text("[data-stat-label]", label, row);
      text("[data-stat-value]", value, row);
      container?.append(row);
    });
  }

  function renderRecent(enrollments, users) {
    const container = $("#recent");
    container?.replaceChildren();
    setVisibility("#recentEmpty", !enrollments.length);
    enrollments
      .slice(-5)
      .reverse()
      .forEach((record) => {
        const row = clone("recentRowTemplate");
        if (!row) return;
        text(
          "[data-recent-student]",
          fullName(users.find((user) => user.id === record.studentId)) ||
            record.studentId,
          row,
        );
        status($("[data-recent-status]", row), record.status);
        container?.append(row);
      });
  }

  function renderAnnouncements(announcements) {
    const container = $("#recentAnnouncements");
    container?.replaceChildren();
    setVisibility("#announcementsEmpty", !announcements.length);
    announcements
      .slice(-5)
      .reverse()
      .forEach((announcement) => {
        const row = clone("announcementRowTemplate");
        if (!row) return;
        text("[data-announcement-title]", announcement.title, row);
        text("[data-announcement-date]", formatDate(announcement.date), row);
        text(
          "[data-announcement-meta]",
          `${announcement.category || "General"} · ${announcement.audience === "all" ? "Everyone" : announcement.audience}`,
          row,
        );
        container?.append(row);
      });
  }

  function broadcast(parent, users) {
    const form = $("#announcementForm");
    if (!form) return;
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const title = $("#announcementTitle")?.value.trim();
      const message = $("#announcementMessage")?.value.trim();
      const category = $("#announcementCategory")?.value || "General";
      const audience = $("#announcementAudience")?.value || "all";
      if (!title || !message) return;
      const announcement = {
        id: DG.generateId("ANN"),
        title,
        message,
        category,
        audience,
        date: new Date().toISOString(),
        authorId: parent.id,
      };
      const announcements = DG.getData("announcements", []);
      announcements.push(announcement);
      DG.saveData("announcements", announcements);
      const notifications = DG.getData("notifications", []);
      users
        .filter((user) => audience === "all" || user.role === audience)
        .forEach((user) =>
          notifications.push({
            id: DG.generateId("NOT"),
            userId: user.id,
            title,
            message,
            date: announcement.date,
            read: false,
            source: "announcement",
            announcementId: announcement.id,
          }),
        );
      DG.saveData("notifications", notifications);
      const feedback = $("#announcementFeedback");
      if (feedback)
        feedback.textContent = `Published to ${audience === "all" ? "everyone" : `${audience}s`}.`;
      form.reset();
      renderAnnouncements(announcements);
      APP.updateNotif();
      APP.toast("Announcement published");
    });
  }

  function init() {
    const parent = AUTH.requireRole("admin");
    if (!parent) return;
    const users = DG.getData("users", []);
    const enrollments = DG.getData("enrollments", []);
    const documents = DG.getData("documentRequests", []);
    const grades = DG.getData("grades", []);
    const competencies = DG.getData("competencies", []);
    const notifications = DG.getData("notifications", []);
    const attendance = DG.getData("attendance", []);
    const avatar = $("#avatar");
    if (avatar) {
      avatar.src = getProfilePhoto(parent);
      avatar.alt = `${fullName(parent)} profile photo`;
    }
    APP.applyTheme();
    APP.updateNotif();
    lucide.createIcons();
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
    const pendingEnrollments = enrollments.filter((record) =>
      ["Submitted", "Under Review", "Draft"].includes(record.status),
    );
    const pendingDocuments = documents.filter((record) =>
      ["Pending", "Processing"].includes(record.status),
    );
    text("#students", users.filter((user) => user.role === "student").length);
    text("#teachers", users.filter((user) => user.role === "teacher").length);
    text("#enrollments", pendingEnrollments.length);
    text("#docs", pendingDocuments.length);
    renderQueue(
      "#enrollmentQueue",
      "#enrollmentQueueEmpty",
      pendingEnrollments,
      users,
      "enrollment.html",
    );
    renderQueue(
      "#documentQueue",
      "#documentQueueEmpty",
      pendingDocuments,
      users,
      "documents.html",
    );
    renderStats(
      users,
      enrollments,
      documents,
      grades,
      competencies,
      notifications,
      attendance,
    );
    renderRecent(enrollments, users);
    renderAttendance(attendance);
    renderAnnouncements(DG.getData("announcements", []));
    broadcast(parent, users);
    $("[data-focus-broadcast]")?.addEventListener("click", () =>
      $("#broadcastSection")?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      }),
    );
    lucide.createIcons();
  }

  window.ADMIN_DASHBOARD = { init };
})();

ADMIN_DASHBOARD.init();
