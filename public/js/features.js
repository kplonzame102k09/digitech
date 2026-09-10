(function () {
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const esc = (value) => APP.esc(value ?? "");
  const userName = (u) => `${u?.firstName || ""} ${u?.lastName || ""}`.trim() || u?.id || "Unknown";
  const photoUrl = (u) => {
    const photo = u?.photo;
    if (!photo) return "";
    if (/^(?:https?:|data:|\/)/.test(photo)) return photo;
    return `${window.location.origin}/storage/${photo.replace(/^\/+/, "")}`;
  };
  const users = () => get("users", []);
  const students = () => users().filter((u) => u.role === "student");
  const teacherStudents = (teacher) => {
    const ids = new Set();
    get("enrollments", []).filter((r) => r.assignedTeacherId === teacher.id || r.teacherId === teacher.id || r.assignedTeacher === teacher.id || r.teacher === userName(teacher)).forEach((r) => ids.add(r.studentId));
    get("grades", []).filter((r) => r.teacherId === teacher.id || r.teacher === userName(teacher)).forEach((r) => ids.add(r.studentId));
    get("competencies", []).filter((r) => r.teacherId === teacher.id || r.assessorId === teacher.id || r.assessor === userName(teacher)).forEach((r) => ids.add(r.studentId));
    return students().filter((s) => ids.has(s.id));
  };
  const notify = (userIds, title, message, source = "portal") =>
    APP.notifyUsers(userIds || [], title, message, source);
  const parentIdsFor = (studentIds) => users().filter((u) => u.role === "parent" && [u.childId, ...(u.childIds || []), ...(u.children || [])].some((v) => studentIds.includes(typeof v === "object" ? v.id || v.studentId : v))).map((u) => u.id);
  const download = (name, text, type = "text/plain") => {
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([text], { type })); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 500);
  };
  const csv = (headers, rows) => [headers, ...rows].map((r) => r.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
  const GRADE_TERMS = ["prelim", "midterm", "finals"];
  const GRADE_WEIGHTS = { prelim: 0.2, midterm: 0.3, finals: 0.5 };
  const SEMESTERS = ["1st Semester", "2nd Semester"];
  const termValue = (g, term) => {
    const v = g && g[term];
    return v === null || v === undefined || v === "" ? null : Number(v);
  };
  const finalGrade = (g) => {
    const values = GRADE_TERMS.map((t) => termValue(g, t));
    if (values.every((v) => v === null)) {
      const f = g && g.finalGrade;
      return f === null || f === undefined || f === "" ? null : Number(f);
    }
    return Math.round(values.reduce((sum, v, i) => sum + (v || 0) * GRADE_WEIGHTS[GRADE_TERMS[i]], 0) * 100) / 100;
  };
  const generalAverage = (list) => {
    const eligible = (list || []).filter((g) => finalGrade(g) !== null && Number(g && g.units) > 0);
    if (!eligible.length) return null;
    const totalUnits = eligible.reduce((sum, g) => sum + Number(g.units), 0);
    return Math.round(eligible.reduce((sum, g) => sum + finalGrade(g) * Number(g.units), 0) / totalUnits * 100) / 100;
  };
  const gwa = (average) => {
    if (average === null || average === undefined) return null;
    if (average >= 97) return 1.0;
    if (average >= 94) return 1.25;
    if (average >= 91) return 1.5;
    if (average >= 88) return 1.75;
    if (average >= 85) return 2.0;
    if (average >= 82) return 2.25;
    if (average >= 79) return 2.5;
    if (average >= 76) return 2.75;
    if (average >= 75) return 3.0;
    return 5.0;
  };
  const gwaScale = { 1.0: "1.00", 1.25: "1.25", 1.5: "1.50", 1.75: "1.75", 2.0: "2.00", 2.25: "2.25", 2.5: "2.50", 2.75: "2.75", 3.0: "3.00", 5.0: "5.00" };
  const gwaText = (value) => (value === null || value === undefined ? "—" : gwaScale[value] ?? String(value));
  const gradesFor = (list, studentId, semester, schoolYear) => (list || []).filter((g) => (!studentId || g.studentId === studentId) && (!semester || (g.semester || "1st Semester") === semester) && (!schoolYear || !g.schoolYear || g.schoolYear === schoolYear));
  const studentAverages = (list, studentId, schoolYear) => {
    const first = generalAverage(gradesFor(list, studentId, "1st Semester", schoolYear));
    const second = generalAverage(gradesFor(list, studentId, "2nd Semester", schoolYear));
    const annualValues = [first, second].filter((v) => v !== null);
    const annual = annualValues.length ? Math.round(annualValues.reduce((s, v) => s + v, 0) / annualValues.length * 100) / 100 : null;
    return { first, second, annual, firstGwa: gwa(first), secondGwa: gwa(second), annualGwa: gwa(annual) };
  };
  window.FEATURES = { get, save, esc, userName, photoUrl, users, students, teacherStudents, notify, parentIdsFor, download, csv, SEMESTERS, GRADE_TERMS, GRADE_WEIGHTS, termValue, finalGrade, generalAverage, gwa, gwaText, gradesFor, studentAverages };
})();
