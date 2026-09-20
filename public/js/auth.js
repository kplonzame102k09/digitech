const DASH = {
  student: "/student/dashboard",
  parent: "/parent/dashboard",
  teacher: "/teacher/dashboard",
  admin: "/admin/dashboard",
  guest: "/guest/announcements",
};

function requireRole(roles) {
  const u = DG.getCurrentUser();
  const allowed = Array.isArray(roles) ? roles : [roles];
  if (!u) {
    location.href = "/login";
    return null;
  }
  if (!allowed.includes(u.role)) {
    location.href = DASH[u.role] || "/login";
    return null;
  }
  return u;
}

function logout() {
  DG.logoutUser();
}

function resolvedEnrollment() {
  const rows = DG.getData("enrollments", []);
  return Array.isArray(rows) && rows.length ? rows[0] : null;
}

function isStudentEnrolled() {
  return resolvedEnrollment()?.status === "Enrolled";
}

function gateEnrolled(requiredByThisPage) {
  const enrolled = isStudentEnrolled();
  if (!enrolled) {
    document
      .querySelectorAll("[data-enroll-blocked]")
      .forEach((el) => {
        el.classList.add("enroll-locked");
        el.title = "Available after enrollment";
      });
  }
  if (requiredByThisPage && !enrolled) {
    location.replace(DASH.student);
    return false;
  }
  return true;
}

window.AUTH = {
  DASH,
  requireRole,
  logout,
  resolvedEnrollment,
  isStudentEnrolled,
  gateEnrolled,
};
