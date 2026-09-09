(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const esc = (value) => (window.APP ? APP.esc(value ?? "") : String(value ?? ""));
  const G = () => window.FEATURES && window.FEATURES.grades || (window.FEATURES || {});
  let admin;
  let users = [];
  let grades = [];
  let selectedStudent = null;
  let modalGrades = [];
  let modalYear = "";
  let modalSemester = "1st Semester";
  let query = "";
  let sortBy = "name";

  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const setText = (selector, value, root = document) => {
    const el = typeof selector === "string" ? $(selector, root) : selector;
    if (el) el.textContent = value ?? "";
  };
  const fullName = (user) => `${user?.firstName || ""} ${user?.lastName || ""}`.trim() || user?.id || "Unknown student";
  const initials = (user) => `${user?.firstName?.[0] || ""}${user?.lastName?.[0] || ""}`.toUpperCase() || "DG";
  const activeSemester = () => $("#semesterFilter")?.value || "1st Semester";
  const termValue = (g, term) => G().termValue ? G().termValue(g, term) : null;
  const finalGrade = (g) => (G().finalGrade ? G().finalGrade(g) : (g && g.finalGrade) || null);
  const generalAverage = (list) => (G().generalAverage ? G().generalAverage(list) : null);
  const gwa = (avg) => (G().gwa ? G().gwa(avg) : null);
  const gwaText = (value) => (G().gwaText ? G().gwaText(value) : value ?? "—");
  const gradeText = (value) => (value === null || value === undefined ? "" : Number(value));

  const studentFor = (id) => users.find((u) => u.id === id);
  const teacherName = (id) => {
    const user = users.find((u) => u.id === id);
    return user ? fullName(user) : "";
  };
  const teacherFor = (studentId, semester) => {
    const records = grades.filter((g) => g.studentId === studentId && (!semester || (g.semester || "1st Semester") === semester));
    const teachers = [
      ...new Set(
        records
          .map((g) => g.teacherId)
          .filter(Boolean)
          .map((id) => teacherName(id))
          .filter(Boolean),
      ),
    ];
    if (teachers.length) return teachers.join(", ");
    const enrollment = get("enrollments", []).find((e) => e.studentId === studentId);
    if (enrollment?.assignedTeacherId) return teacherName(enrollment.assignedTeacherId) || "Unassigned";
    const enrollmentTeacher = [enrollment?.assignedTeacher, enrollment?.teacherId].filter(Boolean).map((id) => teacherName(id)).filter(Boolean);
    if (enrollmentTeacher.length) return enrollmentTeacher.join(", ");
    return records.length ? "Unassigned" : "—";
  };
  const studentsWithGrades = () => {
    const ids = [...new Set(grades.map((g) => g.studentId))];
    return ids
      .map((id) => studentFor(id) || { id, firstName: id, lastName: "" })
      .sort((a, b) => fullName(a).localeCompare(fullName(b)));
  };

  function bindPhoto(photoEl, initialsEl, student) {
    const defaultPhoto = "/images/16432.png";
    if (photoEl && student) {
      photoEl.src = student.photo || defaultPhoto;
      photoEl.alt = `${fullName(student)} profile photo`;
      photoEl.classList.remove("hidden");
      initialsEl?.classList.add("hidden");
      photoEl.onerror = () => {
        if (photoEl.src.endsWith(defaultPhoto)) return;
        photoEl.src = defaultPhoto;
      };
    } else if (initialsEl && student) {
      initialsEl.textContent = initials(student);
      initialsEl.classList.remove("hidden");
      initialsEl.classList.add("flex");
    }
  }

  function renderStats() {
    const semester = activeSemester();
    const yearRecords = semester ? grades.filter((g) => (g.semester || "1st Semester") === semester) : grades;
    const perStudent = [...new Set(yearRecords.map((g) => g.studentId))];
    const averages = perStudent
      .map((id) => generalAverage(yearRecords.filter((g) => g.studentId === id)))
      .filter((v) => v !== null);
    const overallAverage = averages.length ? Math.round(averages.reduce((s, v) => s + v, 0) / averages.length * 100) / 100 : null;
    const published = yearRecords.filter((g) => g.published === true || g.published === "true").length;
    setText("#studentCount", perStudent.length);
    setText("#generalAverage", overallAverage === null ? "—" : overallAverage.toFixed(2));
    setText("#gwaCount", gwaText(gwa(overallAverage)));
    setText("#publishedCount", published);
  }

  function renderRows() {
    const semester = activeSemester();
    const container = $("#rows");
    container?.replaceChildren();
    const q = query.trim().toLowerCase();
    const students = studentsWithGrades().filter((student) => {
      const semesterRecords = grades.some((g) => g.studentId === student.id && (!semester || (g.semester || "1st Semester") === semester));
      if (!semesterRecords) return false;
      if (!q) return true;
      const teacher = teacherFor(student.id, semester).toLowerCase();
      return [fullName(student), student.id, teacher].some((value) => value.toLowerCase().includes(q));
    });
    const avgBy = (student) => generalAverage(grades.filter((g) => g.studentId === student.id && (!semester || (g.semester || "1st Semester") === semester)));
    students.sort((a, b) => {
      if (sortBy === "name") return fullName(a).localeCompare(fullName(b));
      if (sortBy === "nameDesc") return fullName(b).localeCompare(fullName(a));
      if (sortBy === "id") return String(a.id).localeCompare(String(b.id));
      const ga = avgBy(a) ?? -1;
      const gb = avgBy(b) ?? -1;
      return sortBy === "gwa" ? ga - gb || fullName(a).localeCompare(fullName(b)) : gb - ga || fullName(a).localeCompare(fullName(b));
    });
    $("#emptyState")?.classList.toggle("hidden", students.length > 0);
    students.forEach((student) => {
      const rowEl = (document.getElementById("studentRowTemplate")?.content) ? document.importNode(document.getElementById("studentRowTemplate").content, true).firstElementChild : null;
      if (!rowEl) return;
      bindPhoto($("[data-student-photo]", rowEl), $("[data-student-initials]", rowEl), student);
      setText("[data-student-name]", fullName(student), rowEl);
      setText("[data-student-id]", student.id, rowEl);
      setText("[data-student-teacher]", teacherFor(student.id, semester), rowEl);
      const records = grades.filter((g) => g.studentId === student.id && (!semester || (g.semester || "1st Semester") === semester));
      const avg = generalAverage(records);
      setText("[data-student-gwa]", gwaText(gwa(avg)), rowEl);
      $("[data-action=view]", rowEl)?.addEventListener("click", () => openModal(student, semester || "1st Semester"));
      container?.append(rowEl);
    });
    lucide.createIcons();
  }

  function render() {
    renderStats();
    renderRows();
  }

  function schoolYears() {
    return [...new Set(grades.map((g) => g.schoolYear).filter(Boolean))].sort().reverse();
  }

  function openModal(student, semester) {
    selectedStudent = student;
    const years = schoolYears();
    modalYear = $("#modalYear").value || years[0] || "2026-2027";
    modalSemester = ["1st Semester", "2nd Semester"].includes(semester) ? semester : "1st Semester";
    const yearSelect = $("#modalYear");
    yearSelect.replaceChildren();
    (years.length ? years : ["2026-2027"]).forEach((year) => {
      const option = document.createElement("option");
      option.value = year;
      option.textContent = year;
      yearSelect.append(option);
    });
    yearSelect.value = modalYear;
    $("#modalSemester").value = modalSemester;
    setText("[data-grade-student]", fullName(student));
    setText("[data-grade-student-id]", student.id);
    const izq = get("enrollments", []).find((e) => e.studentId === student.id);
    setText("[data-grade-class]", [izq?.gradeLevel, izq?.strand || izq?.track].filter(Boolean).join(" · ") || "");
    bindPhoto($("#gradesModal [data-grade-photo]"), $("#gradesModal [data-grade-initials]"), student);
    loadModalGrades();
    $("#gradesModal")?.showModal();
  }

  function loadModalGrades() {
    modalGrades = grades
      .filter((g) => g.studentId === selectedStudent.id && (!g.schoolYear || g.schoolYear === modalYear) && (g.semester || "1st Semester") === modalSemester)
      .map((g) => ({ ...g, published: g.published === true || g.published === "true" }));
    renderModalRows();
  }

  function renderModalRows() {
    const body = $("#modalGradeRows");
    body?.replaceChildren();
    const template = document.getElementById("modalGradeRowTemplate");
    modalGrades.forEach((g, index) => {
      const row = template ? document.importNode(template.content, true).firstElementChild : null;
      if (!row) return;
      $("[data-row-subject]", row).value = g.subject || "";
      $("[data-row-units]", row).value = g.units ?? 1;
      $("[data-row-prelim]", row).value = gradeText(termValue(g, "prelim"));
      $("[data-row-midterm]", row).value = gradeText(termValue(g, "midterm"));
      $("[data-row-finals]", row).value = gradeText(termValue(g, "finals"));
      $("[data-row-published]", row).value = g.published ? "true" : "false";
      renderRowFinal(row, g);
      $$("[data-row-subject], [data-row-units], [data-row-prelim], [data-row-midterm], [data-row-finals]", row).forEach((input) =>
        input.addEventListener("input", () => {
          g.subject = $("[data-row-subject]", row).value.trim();
          g.units = Number($("[data-row-units]", row).value || 1);
          g.prelim = inputValue($("[data-row-prelim]", row));
          g.midterm = inputValue($("[data-row-midterm]", row));
          g.finals = inputValue($("[data-row-finals]", row));
          renderRowFinal(row, g);
          renderModalSummary();
        }),
      );
      $("[data-row-published]", row)?.addEventListener("change", (event) => {
        g.published = event.target.value === "true";
        if (g.published) {
          g.publishedAt = new Date().toISOString();
          g.publishedBy = admin.id;
        }
      });
      $("[data-row-remove]", row)?.addEventListener("click", () => {
        modalGrades.splice(index, 1);
        renderModalRows();
      });
      body?.append(row);
    });
    renderModalSummary();
  }

  function inputValue(input) {
    const value = input.value;
    return value === "" ? null : Number(value);
  }

  function renderRowFinal(row, g) {
    const final = finalGrade(g);
    setText("[data-row-final]", final === null ? "—" : final.toFixed(2), row);
  }

  function renderModalSummary() {
    const avg = generalAverage(modalGrades);
    const units = modalGrades.reduce((sum, g) => sum + Number(g.units || 0), 0);
    setText("#modalUnits", units.toFixed(2));
    setText("#modalAverage", avg === null ? "—" : avg.toFixed(2));
    setText("#modalGwa", gwaText(gwa(avg)));
    const annual = G().studentAverages ? G().studentAverages(grades, selectedStudent.id, modalYear) : null;
    const hasSecond = grades.some((g) => g.studentId === selectedStudent.id && g.schoolYear === modalYear && (g.semester || "") === "2nd Semester");
    $("#annualSummary")?.classList.toggle("hidden", !annual || annual.annual === null && annual.first === null);
    setText("[data-annual-first]", gwaText(annual?.firstGwa));
    setText("[data-annual-second]", gwaText(annual?.secondGwa));
    setText("[data-annual-average]", annual?.annual === null || annual?.annual === undefined ? "—" : annual.annual.toFixed(2));
    setText("[data-annual-gwa]", gwaText(annual?.annualGwa));
  }

  function addSubjectRow() {
    const record = {
      id: DG.generateId("GRD"),
      studentId: selectedStudent.id,
      subject: "",
      semester: modalSemester,
      schoolYear: modalYear,
      units: 1,
      prelim: null,
      midterm: null,
      finals: null,
      finalGrade: null,
      published: false,
      publishedAt: null,
      publishedBy: null,
      remarks: null,
      notes: null,
      teacherId: admin.id,
      updatedBy: admin.id,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    modalGrades.push(record);
    renderModalRows();
  }

  function saveGrades() {
    if (!selectedStudent) return;
    modalGrades.forEach((g) => {
      const final = finalGrade(g);
      g.finalGrade = final;
      const index = grades.findIndex((item) => item.id === g.id);
      if (index !== -1) {
        grades[index] = { ...grades[index], ...g, updatedAt: new Date().toISOString(), updatedBy: admin.id };
      } else {
        grades.push(g);
      }
    });
    save("grades", grades);
    $("#gradesModal")?.close();
    APP.toast("Grades saved");
    render();
  }

  function exportGrades() {
    const semester = activeSemester();
    const rows = [
      ["Student", "Student ID", "Teacher", "Subject", "Semester", "Units", "Prelim", "Midterm", "Finals", "Final Grade", "Published"],
      ...grades
        .filter((g) => !semester || (g.semester || "1st Semester") === semester)
        .map((g) => [fullName(studentFor(g.studentId)), g.studentId, teacherName(g.teacherId) || teacherFor(g.studentId, semester), g.subject || "", g.semester || "1st Semester", g.units ?? 1, gradeText(termValue(g, "prelim")), gradeText(termValue(g, "midterm")), gradeText(termValue(g, "finals")), gradeText(finalGrade(g)), g.published ? "Yes" : "No"]),
    ];
    const csv = rows.map((row) => row.map((value) => `"${String(value ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    link.download = "digitech-grades.csv";
    link.click();
    URL.revokeObjectURL(link.href);
    APP.toast("Grade CSV downloaded");
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
    $("#open")?.addEventListener("click", () => $("#side")?.classList.toggle("-translate-x-full"));
    $$("[data-notifications]").forEach((button) => button.addEventListener("click", () => APP.showNotifications()));
    $$("[data-theme-toggle]").forEach((button) => button.addEventListener("click", () => APP.toggleTheme()));
    $$("[data-logout]").forEach((button) => button.addEventListener("click", () => AUTH.logout()));
    $("#semesterFilter")?.addEventListener("change", () => { render(); });
    $("#searchInput")?.addEventListener("input", (event) => {
      query = event.target.value;
      renderRows();
    });
    $("#sortBy")?.addEventListener("change", (event) => {
      sortBy = event.target.value;
      renderRows();
    });
    $("#modalSemester")?.addEventListener("change", (event) => {
      modalSemester = event.target.value;
      loadModalGrades();
    });
    $("#modalYear")?.addEventListener("change", (event) => {
      modalYear = event.target.value;
      loadModalGrades();
    });
    $("[data-add-subject]")?.addEventListener("click", addSubjectRow);
    $("[data-save-grades]")?.addEventListener("click", saveGrades);
    $("[data-export-grades]")?.addEventListener("click", exportGrades);
    $$("[data-close-grades]").forEach((button) => button.addEventListener("click", () => $("#gradesModal")?.close()));
    render();
  }
  window.ADMIN_GRADES = { init };
})();

ADMIN_GRADES.init();