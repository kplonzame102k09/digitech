const STORAGE_KEYS = [
  "users", "currentUser", "enrollments", "documentRequests", "grades",
  "competencies", "notifications", "announcements", "attendance", "auditLogs",
  "requirements", "parentLinkRequests", "settings",
];
const memoryStore = Object.create(null);
let syncEnabled = false;
let csrfToken = "";
let apiBase = "/api/portal";
let logoutUrl = "/logout";
const pendingSync = Object.create(null);

function saveData(key, data) {
  memoryStore[key] = data;
  if (syncEnabled && key !== "currentUser") queueSync(key, data);
  return data;
}
function getData(key, fallback = []) {
  if (!Object.prototype.hasOwnProperty.call(memoryStore, key)) memoryStore[key] = fallback;
  return memoryStore[key] ?? fallback;
}
function removeData(key) {
  delete memoryStore[key];
  if (syncEnabled && key !== "currentUser") queueSync(key, []);
}
function clearData(key) { saveData(key, []); }
function queueSync(key, data) {
  pendingSync[key] = data;
  if (queueSync._timer) clearTimeout(queueSync._timer);
  queueSync._timer = setTimeout(flushSync, 200);
}
async function flushSync() {
  const entries = Object.entries(pendingSync);
  entries.forEach(([key]) => delete pendingSync[key]);
  for (const [key, value] of entries) {
    try {
      const response = await fetch(`${apiBase}/${encodeURIComponent(key)}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
        body: JSON.stringify({ value }),
      });
      if (!response.ok) throw new Error(`Sync failed with status ${response.status}`);
    } catch (error) {
      console.error("Failed to sync", key, error);
      pendingSync[key] = value;
    }
  }
  if (Object.keys(pendingSync).length) queueSync._retry = setTimeout(flushSync, 1500);
}
function hydrateFromBoot(boot) {
  if (!boot || typeof boot !== "object") return;
  csrfToken = boot.csrf || "";
  apiBase = boot.apiBase || "/api/portal";
  logoutUrl = boot.logoutUrl || "/logout";
  Object.entries(boot.collections || {}).forEach(([key, value]) => { memoryStore[key] = value; });
  if (boot.currentUser) memoryStore.currentUser = boot.currentUser;
  syncEnabled = true;
  window.__DIGITECH_READY__ = true;
  document.dispatchEvent(new CustomEvent("digitech:ready"));
}
function generateId(prefix = "REC") { return `${prefix}-${new Date().getFullYear()}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`; }
function userIdExists(id) { return getData("users", []).some((u) => u.id === id); }
function generateUserId(role) {
  const prefixes = { student: "STU", parent: "PRT", teacher: "TCH", admin: "ADM", guest: "GST" };
  let id;
  do { id = `${prefixes[role] || "USR"}-${new Date().getFullYear()}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`; } while (userIdExists(id));
  return id;
}
function getCurrentUser() { return memoryStore.currentUser || null; }
function setCurrentUser(user) { memoryStore.currentUser = user; return user; }
function logoutUser() {
  syncEnabled = false;
  delete memoryStore.currentUser;
  const form = document.createElement("form");
  form.method = "POST";
  form.action = logoutUrl || "/logout";
  form.style.display = "none";
  const token = document.createElement("input");
  token.type = "hidden";
  token.name = "_token";
  token.value = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || "";
  form.appendChild(token);
  document.body.appendChild(form);
  form.submit();
}
function getProfilePhoto(user = getCurrentUser()) { return user?.photo || "/images/16432.png"; }
function setProfilePhoto(photo, user = getCurrentUser()) {
  if (!user) return null;
  const updated = { ...user, photo };
  setCurrentUser(updated);
  const users = getData("users", []);
  const index = users.findIndex((candidate) => candidate.id === user.id);
  if (index !== -1) {
    const nextUsers = [...users];
    nextUsers[index] = { ...nextUsers[index], photo };
    saveData("users", nextUsers);
  }
  return updated;
}
async function uploadProfilePhoto(file) {
  if (!file) throw new Error("No photo selected");
  const form = new FormData();
  form.append("photo", file);
  const response = await fetch(`${apiBase}/photo`, {
    method: "POST",
    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
    credentials: "same-origin",
    body: form,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || `Upload failed (${response.status})`);
  if (data.photo) {
    setProfilePhoto(data.photo);
  }
  return data;
}
async function uploadImage(file) {
  if (!file) throw new Error("No image selected");
  const form = new FormData();
  form.append("image", file);
  const response = await fetch(`${apiBase}/uploads`, {
    method: "POST",
    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
    credentials: "same-origin",
    body: form,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || `Upload failed (${response.status})`);
  return data;
}
function loadProfileElements() {
  const user = getCurrentUser();
  if (!user) return;
  document.querySelectorAll("[data-profile-photo]").forEach((img) => { img.src = getProfilePhoto(user); img.alt = `${user.firstName} ${user.lastName}`; });
  document.querySelectorAll("[data-user-name]").forEach((el) => { el.textContent = `${user.firstName} ${user.lastName}`; });
  document.querySelectorAll("[data-user-id]").forEach((el) => { el.textContent = user.id; });
}
window.DG = { saveData, getData, removeData, clearData, generateId, generateUserId, userIdExists, getCurrentUser, setCurrentUser, logoutUser, getProfilePhoto, setProfilePhoto, uploadProfilePhoto, uploadImage, loadProfileElements, hydrateFromBoot, flushSync, STORAGE_KEYS };
