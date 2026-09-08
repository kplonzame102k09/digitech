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

const memoryStore = Object.create(null);
let syncEnabled = false;
let csrfToken = "";
let apiBase = "/api/portal";
let logoutUrl = "/logout";
const pendingSync = Object.create(null);

function readLocal(key, fallback) {
  try {
    const v = JSON.parse(localStorage.getItem(key));
    return v ?? fallback;
  } catch {
    return fallback;
  }
}

function writeLocal(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
  } catch {
    /* ignore quota / private mode */
  }
}

function saveData(key, data) {
  memoryStore[key] = data;
  writeLocal(key, data);
  if (syncEnabled && key !== "currentUser") {
    queueSync(key, data);
  }
  return data;
}

function getData(key, fallback = []) {
  if (Object.prototype.hasOwnProperty.call(memoryStore, key)) {
    return memoryStore[key] ?? fallback;
  }
  const local = readLocal(key, fallback);
  memoryStore[key] = local;
  return local;
}

function removeData(key) {
  delete memoryStore[key];
  localStorage.removeItem(key);
}

function clearData(key) {
  saveData(key, []);
}

function queueSync(key, data) {
  pendingSync[key] = data;
  if (queueSync._timer) clearTimeout(queueSync._timer);
  queueSync._timer = setTimeout(flushSync, 200);
}

async function flushSync() {
  const entries = Object.entries(pendingSync);
  for (const [key] of entries) delete pendingSync[key];

  for (const [key, value] of entries) {
    try {
      const response = await fetch(`${apiBase}/${encodeURIComponent(key)}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: JSON.stringify({ value }),
      });
      if (!response.ok) {
        throw new Error(`Sync failed with status ${response.status}`);
      }
    } catch (err) {
      console.error("Failed to sync", key, err);
      pendingSync[key] = value;
    }
  }
}

function hydrateFromBoot(boot) {
  if (!boot || typeof boot !== "object") return;

  csrfToken = boot.csrf || "";
  apiBase = boot.apiBase || "/api/portal";
  logoutUrl = boot.logoutUrl || "/logout";

  const collections = boot.collections || {};
  Object.keys(collections).forEach((key) => {
    memoryStore[key] = collections[key];
    writeLocal(key, collections[key]);
  });

  if (boot.currentUser) {
    memoryStore.currentUser = boot.currentUser;
    writeLocal("currentUser", boot.currentUser);
  }

  syncEnabled = true;
  window.__DIGITECH_READY__ = true;
  document.dispatchEvent(new CustomEvent("digitech:ready"));
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
  syncEnabled = false;
  removeData("currentUser");

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

function getProfilePhoto(user = getCurrentUser()) {
  return user?.photo || "/images/16432.png";
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
  hydrateFromBoot,
  flushSync,
  STORAGE_KEYS,
};
