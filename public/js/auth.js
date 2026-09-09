const DASH = {
  student: "/student/dashboard",
  parent: "/parent/dashboard",
  teacher: "/teacher/dashboard",
  admin: "/admin/dashboard",
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

window.AUTH = {
  DASH,
  requireRole,
  logout,
};
