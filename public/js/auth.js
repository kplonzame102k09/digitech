const ROLE_PASSWORDS = { 
  admin: "Admin@123", 
  teacher: "Teacher@123" };
const DASH = {
  student: "student/dashboard.html",
  parent: "parent/dashboard.html",
  teacher: "teacher/dashboard.html",
  admin: "admin/dashboard.html",
};
function rolePasswordRequired(role) {
  return role === "teacher" || role === "admin";
}
function requireRole(roles) {
  const u = DG.getCurrentUser();
  const allowed = Array.isArray(roles) ? roles : [roles];
  if (!u) {
    location.href = "../login.html";
    return null;
  }
  if (!allowed.includes(u.role)) {
    location.href = `../${DASH[u.role]}`;
    return null;
  }
  return u;
}
function authUser(value, password, role, rolePassword) {
  const users = DG.getData("users", []);
  const user = users.find(
    (u) =>
      u.id?.toLowerCase() === value.toLowerCase() ||
      u.email?.toLowerCase() === value.toLowerCase() ||
      u.username?.toLowerCase() === value.toLowerCase(),
  );
  if (!user) return { ok: false, msg: "Account not found." };
  if (user.role !== role)
    return {
      ok: false,
      msg: "This account does not belong to the selected role.",
    };
  if (user.password !== password)
    return { ok: false, msg: "Incorrect account password." };
  if (rolePasswordRequired(role) && rolePassword !== ROLE_PASSWORDS[role])
    return { ok: false, msg: `Incorrect ${role} role password.` };
  return { ok: true, user };
}
function logout() {
  DG.logoutUser();
}
function setupRolePassword(select, container, input, label) {
  function sync() {
    const needed = rolePasswordRequired(select.value);
    container.classList.toggle("hidden", !needed);
    input.required = needed;
    if (label)
      label.textContent =
        select.value === "admin"
          ? "Admin Role Password"
          : "Teacher Role Password";
  }
  select.addEventListener("change", sync);
  sync();
}
window.AUTH = {
  ROLE_PASSWORDS,
  DASH,
  rolePasswordRequired,
  requireRole,
  authUser,
  logout,
  setupRolePassword,
};
