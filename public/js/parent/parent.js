(function () {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector));
  const text = (selector, value, root = document) => {
    const element = typeof selector === "string" ? $(selector, root) : selector;
    if (element) element.textContent = value ?? "";
    return element;
  };
  const formatDate = (value) => APP.formatDate(value);
  const page = () => document.body.dataset.parentPage;

  const statusClasses = {
    Approved: ["bg-emerald-50", "text-emerald-700"],
    Enrolled: ["bg-emerald-50", "text-emerald-700"],
    Verified: ["bg-emerald-50", "text-emerald-700"],
    Competent: ["bg-emerald-50", "text-emerald-700"],
    Present: ["bg-emerald-50", "text-emerald-700"],
    Submitted: ["bg-blue-50", "text-blue-700"],
    Processing: ["bg-blue-50", "text-blue-700"],
    Late: ["bg-amber-50", "text-amber-700"],
    "Under Review": ["bg-amber-50", "text-amber-700"],
    Pending: ["bg-amber-50", "text-amber-700"],
    "In Progress": ["bg-amber-50", "text-amber-700"],
    "Ready for Release": ["bg-purple-50", "text-purple-700"],
    Released: ["bg-purple-50", "text-purple-700"],
    Absent: ["bg-red-50", "text-red-700"],
    Rejected: ["bg-red-50", "text-red-700"],
    Failed: ["bg-red-50", "text-red-700"],
    "Not Yet Competent": ["bg-red-50", "text-red-700"],
    "Not Started": ["bg-slate-100", "text-slate-600"],
    Draft: ["bg-slate-100", "text-slate-600"],
  };

  function cloneTemplate(id) {
    const template = document.getElementById(id);
    return template?.content.firstElementChild?.cloneNode(true) || null;
  }

  function statusBadge(element, value) {
    if (!element) return;
    const status = value || "Not Started";
    const classes = statusClasses[status] || statusClasses["Not Started"];
    element.className =
      "inline-flex rounded-full px-2.5 py-1 text-xs font-semibold " +
      classes.join(" ");
    element.textContent = status;
  }

  function initials(user) {
    return (
      `${user?.firstName?.[0] || ""}${user?.lastName?.[0] || ""}`.toUpperCase() ||
      "DG"
    );
  }

  function currentParent() {
    return AUTH.requireRole("parent");
  }

  function allStudents() {
    return DG.getData("users", []).filter((user) => user.role === "student");
  }

  function linkedChildren(parent) {
    if (!parent) return [];
    const ids = [
      parent.childId,
      ...(Array.isArray(parent.childIds) ? parent.childIds : []),
      ...(Array.isArray(parent.children) ? parent.children : []),
    ]
      .filter(Boolean)
      .map((value) =>
        typeof value === "object" ? value.id || value.studentId : value,
      );
    const students = allStudents();
    const byId = students.filter((student) => ids.includes(student.id));
    if (byId.length) return byId;
    const parentName = `${parent.firstName || ""} ${parent.lastName || ""}`
      .trim()
      .toLowerCase();
    return parentName
      ? students.filter(
          (student) =>
            (student.guardianName || "").trim().toLowerCase() === parentName,
        )
      : [];
  }

  function recordsForChildren(key, children) {
    const ids = new Set(children.map((child) => child.id));
    return DG.getData(key, []).filter((record) =>
      ids.has(record.studentId || record.childId),
    );
  }
  function studentName(student) {
    return (
      `${student.firstName || ""} ${student.lastName || ""}`.trim() ||
      student.id
    );
  }

  function enrollmentFor(studentId) {
    return DG.getData("enrollments", []).find(
      (record) => record.studentId === studentId,
    );
  }

  function averageGrade(studentId) {
    const grades = DG.getData("grades", []).filter(
      (grade) =>
        grade.studentId === studentId &&
        grade.published !== false &&
        grade.grade !== null &&
        grade.grade !== "" &&
        !Number.isNaN(Number(grade.grade)),
    );
    if (!grades.length) return null;
    return (
      grades.reduce((sum, grade) => sum + Number(grade.grade), 0) /
      grades.length
    );
  }

  function setVisibility(element, visible) {
    element?.classList.toggle("hidden", !visible);
  }

  function setupShell(parent) {
    text(
      "[data-parent-full-name]",
      `${parent.firstName || ""} ${parent.lastName || ""}`.trim(),
    );
    text("[data-parent-id]", parent.id);
    text("[data-welcome-name]", parent.firstName || "Parent");

    $$("[data-nav-page]").forEach((link) => {
      const active = link.dataset.navPage === page();
      link.classList.toggle("nav-active", active);
      link.classList.toggle("text-slate-600", !active);
      link.classList.toggle("dark:text-slate-300", !active);
      if (active) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });

    $("[data-menu-toggle]")?.addEventListener("click", () =>
      $("#sidebar")?.classList.toggle("-translate-x-full"),
    );
    $$("[data-notifications]").forEach((button) =>
      button.addEventListener("click", () => APP.showNotifications()),
    );
    $$("[data-logout]").forEach((button) =>
      button.addEventListener("click", () => AUTH.logout()),
    );
    $$("[data-theme-toggle]").forEach((button) =>
      button.addEventListener("click", () => APP.toggleTheme()),
    );
    $$("[data-profile-photo]").forEach((image) => {
      image.src = DG.getProfilePhoto(parent);
      image.alt = `${parent.firstName || ""} ${parent.lastName || ""}`.trim();
    });
    APP.applyTheme();
    APP.updateNotif();
    lucide.createIcons();
  }

  function fillChildCard(card, student) {
    const enrollment = enrollmentFor(student.id);
    const average = averageGrade(student.id);
    const competencies = DG.getData("competencies", []).filter((item) => item.studentId === student.id);
    const competent = competencies.filter((item) => item.status === "Competent").length;
    const competencyProgress = competencies.length ? `${Math.round((competent / competencies.length) * 100)}%` : "Not started";
    const photo = $("[data-child-photo]", card);
    const initialsElement = $("[data-child-initials]", card);

    if (photo) {
        photo.src = DG.getProfilePhoto(student);
        photo.alt = `${studentName(student)} profile photo`;

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
    text("[data-child-initials]", initials(student), card);
    text("[data-child-name]", studentName(student), card);
    text("[data-child-id]", student.id, card);
    text(
      "[data-child-strand]",
      student.strand || enrollment?.strand || "Not specified",
      card,
    );
    text(
      "[data-child-average]",
      average === null ? "No grades yet" : average.toFixed(2),
      card,
    );
    text("[data-child-competency]", competencyProgress, card);
    statusBadge(
      $("[data-child-status]", card),
      enrollment?.status || "Not Started",
    );
    const gradesLink = $("[data-grade-link]", card);
    const attendanceLink = $("[data-attendance-link]", card);
    if (gradesLink)
      gradesLink.href = `grades.html?student=${encodeURIComponent(student.id)}`;
    if (attendanceLink)
      attendanceLink.href = `attendance.html?student=${encodeURIComponent(student.id)}`;
  }

  function appendChildCards(container, children) {
    container?.replaceChildren();
    children.forEach((student) => {
      const card = cloneTemplate("childCardTemplate");
      if (card) {
        fillChildCard(card, student);
        container.append(card);
      }
    });
  }

  function renderDashboard(parent, children) {
    const notices = DG.getData("notifications", [])
      .filter((item) => item.userId === parent.id)
      .sort((a, b) => new Date(b.date) - new Date(a.date));
    const grades = recordsForChildren("grades", children).filter(
      (item) =>
        item.published !== false &&
        item.grade !== null &&
        item.grade !== "" &&
        Number.isFinite(Number(item.grade)),
    );
    const average = grades.length
      ? (
          grades.reduce((sum, item) => sum + Number(item.grade), 0) /
          grades.length
        ).toFixed(2)
      : "—";
    const activeEnrollments = children.filter((child) =>
      ["Submitted", "Under Review", "Approved", "Enrolled"].includes(
        enrollmentFor(child.id)?.status,
      ),
    ).length;

    text("#linkedChildrenCount", children.length);
    text("#activeEnrollmentsCount", activeEnrollments);
    text("#gradeAverage", average);
    text("#unreadUpdates", notices.filter((item) => !item.read).length);
    text(
      "#gradeAverageDetail",
      grades.length
        ? `${grades.length} graded subject${grades.length === 1 ? "" : "s"}`
        : "No published grades yet",
    );
    text(
      "#unreadUpdatesDetail",
      notices.some((item) => !item.read)
        ? "Needs your attention"
        : "You are all caught up",
    );

    const childrenOverview = $("#childrenOverview");
    if (children.length) {
      appendChildCards(childrenOverview, children);
      setVisibility($("#childrenOverviewEmpty"), false);
      setVisibility(childrenOverview, true);
    } else {
      setVisibility(childrenOverview, false);
      setVisibility($("#childrenOverviewEmpty"), true);
    }

    const updates = $("#recentUpdates");
    updates?.replaceChildren();
    notices.slice(0, 4).forEach((item) => {
      const update = cloneTemplate("updateTemplate");
      if (update) {
        text("[data-update-title]", item.title || "Portal update", update);
        text("[data-update-date]", formatDate(item.date), update);
        text(
          "[data-update-message]",
          item.message || "No additional details.",
          update,
        );
        updates.append(update);
      }
    });
    setVisibility($("#recentUpdatesEmpty"), !notices.length);
  }

  function renderChildren(children) {
    const container = $("#childrenGrid");
    if (children.length) {
      appendChildCards(container, children);
      setVisibility($("#childrenEmpty"), false);
      setVisibility(container, true);
    } else {
      setVisibility(container, false);
      setVisibility($("#childrenEmpty"), true);
    }
  }

  // function renderStudentFilter(container, children, selected, target) {
  //   container?.replaceChildren();
  //   const all = cloneTemplate("studentFilterTemplate");
  //   if (all) {
  //     const link = $("[data-filter-link]", all);
  //     text("[data-filter-label]", "All children", all);
  //     if (link) link.href = `${target}.html`;
  //     link?.classList.toggle("bg-green-600", !selected);
  //     link?.classList.toggle("text-white", !selected);
  //     container.append(all);
  //   }
  //   children.forEach((child) => {
  //     const filter = cloneTemplate("studentFilterTemplate");
  //     if (!filter) return;
  //     const link = $("[data-filter-link]", filter);
  //     text("[data-filter-label]", studentName(child), filter);
  //     if (link)
  //       link.href = `${target}.html?student=${encodeURIComponent(child.id)}`;
  //     const active = selected === child.id;
  //     link?.classList.toggle("bg-green-600", active);
  //     link?.classList.toggle("text-white", active);
  //     container.append(filter);
  //   });
  // }
  function renderStudentFilter(container, children, selected, target) {
    if (!container) return;

    container.replaceChildren();

    // All children
    const all = cloneTemplate("studentFilterTemplate");

    if (all) {
        const link = $("[data-filter-link]", all);

        text("[data-filter-label]", "All children", all);

        if (link) {
            link.href = `${target}.html`;
            link.removeAttribute("data-student-id");
        }

        link?.classList.toggle("bg-green-600", !selected);
        link?.classList.toggle("text-white", !selected);

        container.append(all);
    }

    // Individual children
    children.forEach((child) => {
        const filter = cloneTemplate("studentFilterTemplate");

        if (!filter) return;

        const link = $("[data-filter-link]", filter);

        text("[data-filter-label]", studentName(child), filter);

        if (link) {
            const studentId = String(child.id || "").trim();

            link.href =
                `${target}.html?student=${encodeURIComponent(studentId)}`;

            link.dataset.studentId = studentId;
        }

        const active =
            selected &&
            String(selected).trim() === String(child.id || "").trim();

        link?.classList.toggle("bg-green-600", active);
        link?.classList.toggle("text-white", active);

        container.append(filter);
    });
}

  function renderAttendance(children) {
    const selected = new URLSearchParams(location.search).get("student");
    const visibleChildren = selected
      ? children.filter((child) => child.id === selected)
      : children;
    const attendance = recordsForChildren("attendance", visibleChildren).sort(
      (a, b) => new Date(b.date) - new Date(a.date),
    );
    renderStudentFilter(
      $("#attendanceFilters"),
      children,
      selected,
      "attendance",
    );
    text(
      "#presentCount",
      attendance.filter(
        (item) => String(item.status).toLowerCase() === "present",
      ).length,
    );
    text(
      "#lateCount",
      attendance.filter((item) => String(item.status).toLowerCase() === "late")
        .length,
    );
    text(
      "#absentCount",
      attendance.filter(
        (item) => String(item.status).toLowerCase() === "absent",
      ).length,
    );

    const table = $("#attendanceTable");
    const rows = $("#attendanceRows");
    rows?.replaceChildren();
    attendance.forEach((item) => {
      const row = cloneTemplate("attendanceRowTemplate");
      if (!row) return;
      const student = children.find((child) => child.id === item.studentId);
      text("[data-attendance-date]", formatDate(item.date), row);
      text(
        "[data-attendance-student]",
        student ? studentName(student) : item.studentId || "—",
        row,
      );
      text(
        "[data-attendance-subject]",
        item.subject || item.session || "—",
        row,
      );
      statusBadge(
        $("[data-attendance-status]", row),
        item.status || "Not recorded",
      );
      text("[data-attendance-remarks]", item.remarks || item.note || "—", row);
      rows.append(row);
    });
    setVisibility(table, attendance.length > 0);
    setVisibility($("#attendanceEmpty"), attendance.length === 0);
  }

  function renderGrades(children) {
    const selected = new URLSearchParams(location.search).get("student");
    const visibleChildren = selected
      ? children.filter((child) => child.id === selected)
      : children;
    const groups = visibleChildren
      .map((student) => ({
        student,
        grades: DG.getData("grades", []).filter(
          (grade) => grade.studentId === student.id && grade.published !== false,
        ),
      }))
      .filter((group) => group.grades.length);
    renderStudentFilter($("#gradesFilters"), children, selected, "grades");
    const container = $("#gradesSections");
    container?.replaceChildren();
    groups.forEach(({ student, grades }) => {
      const group = cloneTemplate("gradeGroupTemplate");
      if (!group) return;
      const published = grades.filter(
        (grade) =>
          grade.grade !== null &&
          grade.grade !== "" &&
          Number.isFinite(Number(grade.grade)),
      );
      const average = published.length
        ? (
            published.reduce((sum, grade) => sum + Number(grade.grade), 0) /
            published.length
          ).toFixed(2)
        : "—";
      text("[data-grade-student]", studentName(student), group);
      text(
        "[data-grade-student-id]",
        `${student.id}${student.strand ? ` · ${student.strand}` : ""}`,
        group,
      );
      text("[data-published-average]", average, group);
      const rows = $("[data-grade-rows]", group);
      grades.forEach((grade) => {
        const row = cloneTemplate("gradeRowTemplate");
        if (!row) return;
        text("[data-grade-subject]", grade.subject || grade.course || "—", row);
        text(
          "[data-grade-term]",
          grade.term || grade.period || "Current term",
          row,
        );
        text(
          "[data-grade-value]",
          grade.grade === null || grade.grade === ""
            ? "Not released"
            : Number(grade.grade).toFixed(2),
          row,
        );
        if (grade.remarks)
          statusBadge($("[data-grade-remarks]", row), grade.remarks);
        else text("[data-grade-remarks]", "—", row);
        rows.append(row);
      });
      container.append(group);
    });
    setVisibility(container, groups.length > 0);
    setVisibility($("#gradesEmpty"), groups.length === 0);
  }

  function renderDocuments(children) {
    const requests = recordsForChildren("documentRequests", children).sort(
      (a, b) =>
        new Date(b.createdAt || b.date) - new Date(a.createdAt || a.date),
    );
    const requirements = recordsForChildren("requirements", children);
    const rows = [
      ...requests.map((item) => ({
        ...item,
        source: "Document request",
        name:
          item.documentType ||
          item.document ||
          item.title ||
          "Requested document",
        when: item.createdAt || item.date,
      })),
      ...requirements.map((item) => ({
        ...item,
        source: "Requirement",
        name: item.name || item.title || item.type || "Requirement",
        when: item.updatedAt || item.date,
      })),
    ];
    const table = $("#documentsTable");
    const body = $("#documentRows");
    body?.replaceChildren();
    rows.forEach((item) => {
      const row = cloneTemplate("documentRowTemplate");
      if (!row) return;
      const student = children.find((child) => child.id === item.studentId);
      text("[data-document-name]", item.name, row);
      text(
        "[data-document-student]",
        student ? studentName(student) : item.studentId || "—",
        row,
      );
      text("[data-document-source]", item.source, row);
      statusBadge($("[data-document-status]", row), item.status || "Pending");
      text("[data-document-date]", formatDate(item.when), row);
      body.append(row);
    });
    setVisibility(table, rows.length > 0);
    setVisibility($("#documentsEmpty"), rows.length === 0);
  }

  function renderAnnouncements(parent) {
    const announcements = DG.getData("announcements", []).sort(
      (a, b) =>
        new Date(b.date || b.createdAt) - new Date(a.date || a.createdAt),
    );
    const notifications = DG.getData("notifications", [])
      .filter((item) => item.userId === parent.id)
      .sort((a, b) => new Date(b.date) - new Date(a.date));
    const items = announcements.length ? announcements : notifications;
    const container = $("#announcementList");
    container?.replaceChildren();
    items.forEach((item) => {
      const announcement = cloneTemplate("announcementTemplate");
      if (!announcement) return;
      text(
        "[data-announcement-title]",
        item.title || "College announcement",
        announcement,
      );
      text(
        "[data-announcement-date]",
        formatDate(item.date || item.createdAt),
        announcement,
      );
      text(
        "[data-announcement-message]",
        item.message || item.body || item.content || "No additional details.",
        announcement,
      );
      text("[data-announcement-category]", item.category || "", announcement);
      setVisibility(
        $("[data-announcement-category]", announcement),
        Boolean(item.category),
      );
      container.append(announcement);
    });
    setVisibility(container, items.length > 0);
    setVisibility($("#announcementsEmpty"), items.length === 0);
  }

  function renderProfile(parent) {
    text(
      "#profileName",
      `${parent.firstName || ""} ${parent.lastName || ""}`.trim(),
    );
    text("#profileId", parent.id);
    text(
      "#profileRelationship",
      `Parent / ${parent.relationship || "Guardian"}`,
    );
    const email = $("#profileEmail");
    const contact = $("#profileContact");
    const occupation = $("#profileOccupation");
    const emergency = $("#profileEmergencyContact");
    const address = $("#profileAddress");
    if (email) email.value = parent.email || "";
    if (contact) contact.value = parent.contact || "";
    if (occupation) occupation.value = parent.occupation || "";
    if (emergency) emergency.value = parent.emergencyContact || "";
    if (address) address.value = parent.address || "";
    $("#profileForm")?.addEventListener("submit", (event) =>
      saveProfile(event, parent),
    );
  }

  function saveProfile(event, parent) {
    event.preventDefault();
    const users = DG.getData("users", []);
    const record = users.find((user) => user.id === parent.id);
    if (!record) return;
    record.contact = $("#profileContact")?.value.trim() || "";
    record.occupation = $("#profileOccupation")?.value.trim() || "";
    record.emergencyContact = $("#profileEmergencyContact")?.value.trim() || "";
    record.address = $("#profileAddress")?.value.trim() || "";
    DG.saveData("users", users);
    DG.setCurrentUser(record);
    APP.toast("Profile updated");
  }

  function init() {
    const parent = currentParent();
    if (!parent) return;
    const children = linkedChildren(parent);
    setupShell(parent);
    if (page() === "dashboard") renderDashboard(parent, children);
    if (page() === "children") renderChildren(children);
    if (page() === "attendance") renderAttendance(children);
    if (page() === "grades") renderGrades(children);
    if (page() === "documents") renderDocuments(children);
    if (page() === "announcements") renderAnnouncements(parent);
    if (page() === "profile") renderProfile(parent);
    lucide.createIcons();
  }

    document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("profilePhotoInput");
    const photos = document.querySelectorAll("[data-profile-photo]");
    if (!input) return;
    input.addEventListener("change", () => {
      const file = input.files?.[0];
      if (!file) return;
      if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        alert("Please select a JPG, PNG, or WebP image.");
        input.value = "";
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        alert("Profile picture must be 2 MB or smaller.");
        input.value = "";
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const photo = reader.result;
        photos.forEach(img => {
          img.src = photo;
        });
        const currentUser = JSON.parse(
          localStorage.getItem("dg_current_user") ||
          localStorage.getItem("currentUser") ||
          "null"
        );
        const parentId =
          currentUser?.id ||
          currentUser?.userId ||
          currentUser?.username ||
          "parent";
        localStorage.setItem(
          `parent_profile_photo_${parentId}`,
          photo
        );
      };
      reader.readAsDataURL(file);
    });
    const currentUser = JSON.parse(
      localStorage.getItem("dg_current_user") ||
      localStorage.getItem("currentUser") ||
      "null"
    );
    const parentId =
      currentUser?.id ||
      currentUser?.userId ||
      currentUser?.username ||
      "parent";
    const savedPhoto = localStorage.getItem(
      `parent_profile_photo_${parentId}`
    );
    if (savedPhoto) {
      photos.forEach(img => {
        img.src = savedPhoto;
      });
    }
    if (window.lucide) {
      lucide.createIcons();
    }
  });
  window.PARENT = { init };
})();
