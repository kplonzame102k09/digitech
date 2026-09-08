(function () {
  const get = (key, fallback = []) => DG.getData(key, fallback);
  const save = (key, value) => DG.saveData(key, value);
  const esc = (value) => APP.esc(value ?? "");
  const userName = (u) => `${u?.firstName || ""} ${u?.lastName || ""}`.trim() || u?.id || "Unknown";
  const users = () => get("users", []);
  const students = () => users().filter((u) => u.role === "student");
  const teacherStudents = (teacher) => {
    const ids = new Set();
    get("enrollments", []).filter((r) => r.assignedTeacherId === teacher.id || r.teacherId === teacher.id || r.assignedTeacher === teacher.id || r.teacher === userName(teacher)).forEach((r) => ids.add(r.studentId));
    get("grades", []).filter((r) => r.teacherId === teacher.id || r.teacher === userName(teacher)).forEach((r) => ids.add(r.studentId));
    get("competencies", []).filter((r) => r.teacherId === teacher.id || r.assessorId === teacher.id || r.assessor === userName(teacher)).forEach((r) => ids.add(r.studentId));
    return students().filter((s) => ids.has(s.id));
  };
  const notify = (userIds, title, message, source = "portal") => {
    const ns = get("notifications", []);
    [...new Set(userIds)].filter(Boolean).forEach((userId) => ns.push({ id: DG.generateId("NOT"), userId, title, message, source, date: new Date().toISOString(), read: false }));
    save("notifications", ns);
  };
  const parentIdsFor = (studentIds) => users().filter((u) => u.role === "parent" && [u.childId, ...(u.childIds || []), ...(u.children || [])].some((v) => studentIds.includes(typeof v === "object" ? v.id || v.studentId : v))).map((u) => u.id);
  const download = (name, text, type = "text/plain") => {
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([text], { type })); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 500);
  };
  const csv = (headers, rows) => [headers, ...rows].map((r) => r.map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`).join(",")).join("\n");
  window.FEATURES = { get, save, esc, userName, users, students, teacherStudents, notify, parentIdsFor, download, csv };
})();
