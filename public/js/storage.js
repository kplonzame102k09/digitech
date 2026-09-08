const STORAGE_KEYS = [
  "users",
  "currentUser",
  "enrollments",
  "documentRequests",
  "grades",
  "competencies",
  "notifications",
  "announcements",
  "attendance",
  "auditLogs",
  "requirements",
  "parentLinkRequests",
  "settings",
];
function saveData(key, data) {
  localStorage.setItem(key, JSON.stringify(data));
  return data;
}
function getData(key, fallback = []) {
  try {
    const v = JSON.parse(localStorage.getItem(key));
    return v ?? fallback;
  } catch {
    return fallback;
  }
}
function removeData(key) {
  localStorage.removeItem(key);
}
function clearData(key) {
  saveData(key, []);
}
function generateId(prefix = "REC") {
  const year = new Date().getFullYear();
  return `${prefix}-${year}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;
}
function userIdExists(id) {
  return getData("users", []).some((u) => u.id === id);
}
function generateUserId(role) {
  const prefixes = {
    student: "STU",
    parent: "PRT",
    teacher: "TCH",
    admin: "ADM",
  };
  let id;
  do {
    id = `${prefixes[role]}-${new Date().getFullYear()}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`;
  } while (userIdExists(id));
  return id;
}
function getCurrentUser() {
  return getData("currentUser", null);
}
function setCurrentUser(user) {
  saveData("currentUser", user);
}
function logoutUser() {
  removeData("currentUser");
  location.href = "../login.html";
}
function getProfilePhoto(user = getCurrentUser()) {
  return user?.photo || "../assets/images/16432.png";
}

function setProfilePhoto(photo, user = getCurrentUser()) {
  if (!user) return null;

  user.photo = photo;
  setCurrentUser(user);

  const users = getData("users", []);
  const index = users.findIndex((u) => u.id === user.id);

  if (index !== -1) {
    users[index].photo = photo;
    saveData("users", users);
  }

  return user;
}
function loadProfileElements() {
  const user = getCurrentUser();
  if (!user) return;

  document.querySelectorAll("[data-profile-photo]").forEach((img) => {
    img.src = getProfilePhoto(user);
    img.alt = `${user.firstName} ${user.lastName}`;
  });

  document.querySelectorAll("[data-user-name]").forEach((el) => {
    el.textContent = `${user.firstName} ${user.lastName}`;
  });

  document.querySelectorAll("[data-user-id]").forEach((el) => {
    el.textContent = user.id;
  });
}
window.DG = {
  saveData,
  getData,
  removeData,
  clearData,
  generateId,
  generateUserId,
  userIdExists,
  getCurrentUser,
  setCurrentUser,
  logoutUser,
  getProfilePhoto,
  setProfilePhoto,
  loadProfileElements,
  STORAGE_KEYS,
};
