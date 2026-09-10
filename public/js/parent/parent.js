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
    Present: ["bg-emerald-50", "text-emerald-700", "dark:bg-emerald-950", "dark:text-emerald-300"],
    Good: ["bg-emerald-50", "text-emerald-700"],
    "Very Good": ["bg-emerald-50", "text-emerald-700"],
    "With Honors": ["bg-emerald-50", "text-emerald-700"],
    "With High Honors": ["bg-emerald-50", "text-emerald-700"],
    "With Highest Honors": ["bg-emerald-50", "text-emerald-700"],
    Submitted: ["bg-blue-50", "text-blue-700"],
    Processing: ["bg-blue-50", "text-blue-700"],
    Late: ["bg-amber-50", "text-amber-700", "dark:bg-amber-950", "dark:text-amber-300"],
    "Under Review": ["bg-amber-50", "text-amber-700"],
    Pending: ["bg-amber-50", "text-amber-700"],
    Incomplete: ["bg-amber-50", "text-amber-700"],
    "In Progress": ["bg-amber-50", "text-amber-700"],
    "Ready for Release": ["bg-purple-50", "text-purple-700"],
    Released: ["bg-purple-50", "text-purple-700"],
    Absent: ["bg-red-50", "text-red-700", "dark:bg-red-950", "dark:text-red-300"],
    Rejected: ["bg-red-50", "text-red-700"],
    Failed: ["bg-red-50", "text-red-700"],
    "Not Yet Competent": ["bg-red-50", "text-red-700"],
    "Not Started": ["bg-slate-100", "text-slate-600"],
    Draft: ["bg-slate-100", "text-slate-600"],
    Passed: ["bg-emerald-50", "text-emerald-700"],
  };

  const GT = () => window.FEATURES || {};
  const gradeTerm = (g, term) => {
    const v = g && g[term];
    return v === null || v === undefined || v === "" ? null : Number(v);
  };
  const gradeFinal = (g) => (GT().finalGrade ? GT().finalGrade(g) : (g && g.finalGrade) || null);
  const gradeAverage = (list) => (GT().generalAverage ? GT().generalAverage(list) : null);
  const gradeGwa = (avg) => (GT().gwa ? GT().gwa(avg) : null);
  const gradeGwaText = (value) => (GT().gwaText ? GT().gwaText(value) : value ?? "—");
  const gradeInput = (value) => (value === null || value === undefined || value === "" ? "" : value);
  const numberOrNull = (value) => {
    if (value === undefined || value === null || value === "") return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
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
        gradeFinal(grade) !== null,
    );
    return gradeAverage(grades);
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
      gradesLink.href = `/parent/grades?student=${encodeURIComponent(student.id)}`;
    if (attendanceLink)
      attendanceLink.href = `/parent/attendance?student=${encodeURIComponent(student.id)}`;
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
      (item) => item.published !== false && gradeFinal(item) !== null,
    );
    const average =
      grades.length && gradeAverage(grades) !== null
        ? gradeAverage(grades).toFixed(2)
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
  //     if (link) link.href = `/parent/${target}`;
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
            link.href = `/parent/${target}`;
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
                `/parent/${target}?student=${encodeURIComponent(studentId)}`;

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
      const initialsEl = $("[data-attendance-initials]", row);
      if (initialsEl) initialsEl.textContent = initials(student);
      const photo = $("[data-attendance-photo]", row);
      if (photo && student?.photo) {
        photo.src = student.photo;
        photo.alt = studentName(student);
        photo.classList.remove("hidden");
      }
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

  function publishedForStudent(studentId) {
    return DG.getData("grades", []).filter(
      (grade) => grade.studentId === studentId && grade.published !== false,
    );
  }

  function parentStudentById(id) {
    return allStudents().find((student) => student.id === id);
  }

  function renderGrades(children) {
    const selected = new URLSearchParams(location.search).get("student");
    const semester = $("semesterFilter")?.value || "1st Semester";
    const visibleChildren = selected
      ? children.filter((child) => child.id === selected)
      : children;
    const groups = visibleChildren
      .map((student) => ({
        student,
        grades: publishedForStudent(student.id).filter(
          (grade) => (grade.semester || "1st Semester") === semester,
        ),
      }))
      .filter((group) => group.grades.length);
    renderStudentFilter($("#gradesFilters"), children, selected, "grades");

    const allGraded = visibleChildren.filter((student) =>
      publishedForStudent(student.id).some(
        (grade) => gradeFinal(grade) !== null && (grade.semester || "1st Semester") === semester,
      ),
    );
    const subjects = groups.reduce(
      (sum, group) => sum + group.grades.filter((grade) => gradeFinal(grade) !== null).length,
      0,
    );
    const averages = groups
      .map(({ grades }) => gradeAverage(grades))
      .filter((value) => value !== null);
    const overall = averages.length
      ? Math.round((averages.reduce((sum, value) => sum + value, 0) / averages.length) * 100) / 100
      : null;

    text("#gradeStudents", allGraded.length);
    text("#gradeSubjects", subjects);
    text("#gradeGwa", overall === null ? "—" : gradeGwaText(gradeGwa(overall)));
    text("#gradeAttention", Math.max(visibleChildren.length - allGraded.length, 0));

    const container = $("#gradesTable");
    const tbody = $("#gradesRows");
    tbody?.replaceChildren();
    groups.forEach(({ student, grades }) => {
      const row = cloneTemplate("gradesRowTemplate");
      if (!row) return;
      const avg = gradeAverage(grades);
      text("[data-grade-initials]", initials(student), row);
      text("[data-grade-student]", studentName(student), row);
      text("[data-grade-student-id]", student.id, row);
      text(
        "[data-grade-average]",
        avg === null ? "—" : gradeGwaText(gradeGwa(avg)),
        row,
      );
      $("[data-view-grade]", row)?.addEventListener("click", () =>
        openGradeModal(student.id),
      );
      tbody?.append(row);
    });
    setVisibility(container, groups.length > 0);
    setVisibility($("#gradesEmpty"), groups.length === 0);
  }

  function openGradeModal(studentId) {
    const student = parentStudentById(studentId);
    if (!student) return;
    const years = [...new Set(DG.getData("grades", []).map((g) => g.schoolYear).filter(Boolean))].sort().reverse();
    const yearSelect = $("#modalYear");
    yearSelect?.replaceChildren();
    (years.length ? years : ["2026-2027"]).forEach((year) => {
      const option = document.createElement("option");
      option.value = year;
      option.textContent = year;
      yearSelect.append(option);
    });
    const enrollment = enrollmentFor(student.id);
    text("#gradesModal [data-grade-student]", studentName(student));
    text("#gradesModal [data-grade-student-id]", `${student.id}${student.strand ? ` · ${student.strand}` : ""}`);
    if (yearSelect) yearSelect.value = years[0] || "2026-2027";
    $("#modalSemester").value = $("semesterFilter")?.value || "1st Semester";
    const photo = $("#gradesModal [data-grade-photo]");
    const initialsEl = $("#gradesModal [data-grade-initials]");
    if (photo) {
      photo.classList.add("hidden");
      const fallback = () => {
        if (initialsEl) {
          initialsEl.textContent = initials(student);
          initialsEl.classList.remove("hidden");
        }
      };
      initialsEl?.classList.add("hidden");
      const src = DG.getProfilePhoto(student);
      if (src) {
        photo.src = src;
        photo.classList.remove("hidden");
        photo.onerror = fallback;
      } else fallback();
    }
    renderGradeModalRows();
    $("#gradesModal")?.showModal();
    lucide.createIcons();
  }

  function modalGradeRecords() {
    const student = parentStudentById(
      ($("#gradesModal [data-grade-student-id]")?.textContent || "").split("·")[0].trim(),
    );
    const year = $("#modalYear")?.value || "";
    const semester = $("#modalSemester")?.value || "1st Semester";
    if (!student) return [];
    return publishedForStudent(student.id)
      .filter(
        (record) =>
          (!record.schoolYear || record.schoolYear === year) &&
          (record.semester || "1st Semester") === semester,
      )
      .sort((a, b) => String(a.subject || "").localeCompare(String(b.subject || "")));
  }

  function renderGradeModalRows() {
    const body = $("#modalGradeRows");
    body?.replaceChildren();
    const records = modalGradeRecords();
    if (!records.length) {
      body.innerHTML =
        '<tr><td colspan="7" class="p-8 text-center text-slate-500">No published grades for this semester yet.</td></tr>';
      renderGradeModalSummary(records);
      return;
    }
    records.forEach((record) => {
      const row = cloneTemplate("gradeRowTemplate");
      if (!row) return;
      const final = gradeFinal(record);
      text("[data-grade-subject]", record.subject || "—", row);
      text("[data-grade-units]", Number(record.units || 0).toFixed(0), row);
      text("[data-grade-prelim]", record.prelim === null || record.prelim === undefined || record.prelim === "" ? "—" : Number(record.prelim).toFixed(2), row);
      text("[data-grade-midterm]", record.midterm === null || record.midterm === undefined || record.midterm === "" ? "—" : Number(record.midterm).toFixed(2), row);
      text("[data-grade-finals]", record.finals === null || record.finals === undefined || record.finals === "" ? "—" : Number(record.finals).toFixed(2), row);
      text("[data-grade-final]", final === null ? "—" : final.toFixed(2), row);
      const remarks = $("[data-grade-remarks]", row);
      if (record.remarks) statusBadge(remarks, record.remarks);
      else text("[data-grade-remarks]", "—", row);
      body?.append(row);
    });
    renderGradeModalSummary(records);
  }

  function renderGradeModalSummary(records) {
    const studentId = ($("#gradesModal [data-grade-student-id]")?.textContent || "").split("·")[0].trim();
    const year = $("#modalYear")?.value || "";
    const annual = GT().studentAverages
      ? GT().studentAverages(DG.getData("grades", []), studentId, year)
      : null;
    text("#annualFirstGwa", annual ? gradeGwaText(annual.firstGwa) : "—");
    text("#annualSecondGwa", annual ? gradeGwaText(annual.secondGwa) : "—");
    text("#annualAverage", annual && annual.annual !== null ? annual.annual.toFixed(2) : "—");
    text("#annualGwa", annual ? gradeGwaText(annual.annualGwa) : "—");
  }

  function exportGrades() {
    const semester = $("semesterFilter")?.value || "1st Semester";
    const selected = new URLSearchParams(location.search).get("student");
    const children = linkedChildren(currentParent());
    const visibleChildren = selected
      ? children.filter((child) => child.id === selected)
      : children;
    const rows = [];
    visibleChildren.forEach((student) => {
      publishedForStudent(student.id)
        .filter((grade) => (grade.semester || "1st Semester") === semester)
        .forEach((grade) => {
          const final = gradeFinal(grade);
          rows.push([
            studentName(student),
            student.id,
            grade.subject || "",
            Number(grade.units || 0).toFixed(0),
            gradeInput(grade.prelim),
            gradeInput(grade.midterm),
            gradeInput(grade.finals),
            final === null ? "" : final.toFixed(2),
            grade.remarks || "",
            semester,
          ]);
        });
    });
    const headers = ["Student", "Student ID", "Subject", "Units", "Prelim", "Midterm", "Finals", "Final Grade", "Remarks", "Semester"];
    const content = (GT().csv || ((h, r) => [h, ...r].map((line) => line.map((cell) => `"${String(cell ?? "").replaceAll('"', '""')}"`).join(",")).join("\n")))(headers, rows);
    (GT().download || ((name, text) => {
      const a = document.createElement("a");
      a.href = URL.createObjectURL(new Blob([text], { type: "text/csv" }));
      a.download = name;
      a.click();
    }))("parent-grades.csv", content);
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
    ]
      .map((item) => {
        const time = item.when ? new Date(item.when) : null;
        return { item, time };
      })
      .sort((a, b) => {
        if (a.time && b.time) return b.time - a.time;
        if (a.time) return -1;
        if (b.time) return 1;
        return 0;
      })
      .map((entry) => entry.item);
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

  function wireLinkRequestForm(parent) {
    const form = $("#linkRequestForm");
    const input = $("#requestedStudentId");
    const status = $("#linkRequestStatus");
    if (!form || !input || !status) return;
    const parentName = `${parent.firstName || ""} ${parent.lastName || ""}`.trim() || parent.id;
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const studentId = input.value.trim();
      if (!studentId) {
        status.textContent = "Enter the student ID to link.";
        return;
      }
      const student = DG.getData("users", []).find((u) => u.id === studentId);
      if (!student) {
        status.textContent = `No student record found for "${studentId}".`;
        return;
      }
      if ((parent.childId || parent.childIds?.includes?.(studentId) || parent.children?.some?.((child) => (typeof child === "object" ? child.id || child.studentId : child) === studentId))) {
        status.textContent = `You are already linked to ${student.firstName || student.id}.`;
        return;
      }
      const requests = DG.getData("parentLinkRequests", []);
      const pending = requests.find((r) => r.parentId === parent.id && r.studentId === studentId && r.status === "Pending");
      if (pending) {
        status.textContent = "A request for this student is already pending review.";
        return;
      }
      const now = new Date().toISOString();
      requests.push({
        id: DG.generateId("PLR"),
        parentId: parent.id,
        studentId,
        status: "Pending",
        createdAt: now,
        updatedAt: now,
      });
      DG.saveData("parentLinkRequests", requests);
      APP?.notifyAdmins?.(
        "Parent link request",
        `${parentName} requested a link to ${studentId} (${student.firstName || ""}).`,
        "parentLinkRequest",
        requests[requests.length - 1].id,
      );
      status.textContent = `A link request for ${studentId} has been sent to the registrar for review.`;
      input.value = "";
    });
  }

  function init() {
    const parent = currentParent();
    if (!parent) return;
    const children = linkedChildren(parent);
    setupShell(parent);
    if (page() === "dashboard") renderDashboard(parent, children);
    if (page() === "children") {
      renderChildren(children);
      wireLinkRequestForm(parent);
    }
    if (page() === "attendance") renderAttendance(children);
    if (page() === "grades") {
      renderGrades(children);
      $("#semesterFilter")?.addEventListener("change", () => renderGrades(children));
      $("#exportVisible")?.addEventListener("click", exportGrades);
      $$("[data-close-grades]").forEach((button) =>
        button.addEventListener("click", () => $("#gradesModal")?.close()),
      );
      $("#modalSemester")?.addEventListener("change", renderGradeModalRows);
      $("#modalYear")?.addEventListener("change", renderGradeModalRows);
    }
    if (page() === "documents") renderDocuments(children);
    if (page() === "announcements") renderAnnouncements(parent);
    if (page() === "profile") renderProfile(parent);
    lucide.createIcons();
  }

    document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("profilePhotoInput");
    const photos = document.querySelectorAll("[data-profile-photo]");
    if (!input) return;
    input.addEventListener("change", async () => {
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
      try {
        await DG.uploadProfilePhoto(file);
      } catch (error) {
        alert(error.message || "Failed to update photo.");
        return;
      } finally {
        input.value = "";
      }
      DG.loadProfileElements();
    });
    const savedPhoto = DG.getProfilePhoto(DG.getCurrentUser());
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
  window.PARENT.init();
})();
