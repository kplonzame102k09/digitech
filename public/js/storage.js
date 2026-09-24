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
// per-key consecutive-failure counter so a persistent server rejection stops
// being retried instead of hammering the endpoint forever.
const syncRetryCount = Object.create(null);
const MAX_SYNC_RETRIES = 5;
const DEFAULT_AVATAR = "/images/16432.png";

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
// Silent write used by the background sync poller: updates memory WITHOUT
// queueing an outbound PUT (we just received this value from the server).
function importData(key, data) {
  memoryStore[key] = data;
  return data;
}
// Keys with unsent local writes; the poller skips these to avoid pull-vs-push races.
function pendingKeys() { return Object.keys(pendingSync); }
function queueSync(key, data) {
  pendingSync[key] = data;
  if (queueSync._timer) clearTimeout(queueSync._timer);
  queueSync._timer = setTimeout(flushSync, 200);
}
async function flushSync() {
  const entries = Object.entries(pendingSync);
  if (!entries.length) return { ok: true, synced: 0, total: 0, failed: [] };
  if (flushSync._busy) return { ok: false, synced: 0, total: 0, failed: entries.map(([key]) => key), busy: true };
  flushSync._busy = true;
  try {
    const failed = [];
    const synced = [];
    for (const [key, value] of entries) {
      // Do NOT delete the key before the PUT resolves: pendingKeys() drives
      // the background poller, which must keep skipping this key while the
      // write is in flight so a stale server snapshot can't overwrite the
      // user's just-saved optimistic value. The key is removed only after its
      // PUT settles.
      try {
        const response = await fetch(`${apiBase}/${encodeURIComponent(key)}`, {
          method: "PUT",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
          body: JSON.stringify({ value }),
        });
        if (!response.ok) throw new Error(`Sync failed with status ${response.status}`);
        // Server-normalized mirror flows back into local memory so the next
        // render matches what was actually persisted.
        const data = await response.json().catch(() => null);
        if (data && Array.isArray(data.value)) importData(key, data.value);
        delete pendingSync[key];
        delete syncRetryCount[key];
        synced.push(key);
      } catch (error) {
        // Permission-denied is permanent for this payload: the account no
        // longer has write access (e.g. auditLogs were removed from a
        // teacher's writable collections), so endlessly re-queueing it would
        // hammer the server. Drop the key instead and keep the rest moving.
        if (error && /403/.test(error.message || "")) {
          console.warn("Skipped syncing", key, "– write access denied");
          delete pendingSync[key];
          delete syncRetryCount[key];
          continue;
        }
        console.error("Failed to sync", key, error);
        const attempts = (syncRetryCount[key] || 0) + 1;
        syncRetryCount[key] = attempts;
        if (attempts >= MAX_SYNC_RETRIES) {
          console.warn("Dropping", key, "after", attempts, "consecutive sync failures");
          delete pendingSync[key];
          delete syncRetryCount[key];
          failed.push(key);
          continue;
        }
        // Preserve the newest local value if the user edited again mid-flight.
        if (!Object.prototype.hasOwnProperty.call(pendingSync, key)) pendingSync[key] = value;
        failed.push(key);
      }
    }
    if (Object.keys(pendingSync).length) {
      if (queueSync._retry) clearTimeout(queueSync._retry);
      queueSync._retry = setTimeout(flushSync, 1500);
    }
    return {
      ok: failed.length === 0,
      synced: synced.length,
      total: entries.length,
      failed,
    };
  } finally {
    flushSync._busy = false;
  }
}
function hydrateFromBoot(boot) {
  if (!boot || typeof boot !== "object") return;
  csrfToken = boot.csrf || "";
  apiBase = boot.apiBase || "/api/portal";
  logoutUrl = boot.logoutUrl || "/logout";
  Object.entries(boot.collections || {}).forEach(([key, value]) => { memoryStore[key] = value; });
  if (Array.isArray(memoryStore.notifications)) {
    memoryStore.notifications = dedupeNotifications(memoryStore.notifications);
  }
  if (boot.currentUser) memoryStore.currentUser = boot.currentUser;
  syncEnabled = true;
  window.__DIGITECH_READY__ = true;
  document.dispatchEvent(new CustomEvent("digitech:ready"));
}
function generateId(prefix = "REC") { return `${prefix}-${new Date().getFullYear()}-${Math.random().toString(36).slice(2, 8).toUpperCase()}`; }
function dedupeNotifications(list) {
  const byKey = new Map();
  (list || []).forEach((notification) => {
    const source = notification.source || "portal";
    const key =
      notification.id ||
      `${source}|${notification[`${source}Id`] || ""}|${notification.title}|${notification.message}`;
    const previous = byKey.get(key);
    if (!previous || new Date(notification.date) >= new Date(previous.date)) {
      byKey.set(key, notification);
    }
  });
  return [...byKey.values()];
}
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
function getProfilePhoto(user = getCurrentUser()) {
  const photo = user?.photo;
  if (!photo) return DEFAULT_AVATAR;
  return normalizePhotoUrl(photo);
}

function normalizePhotoUrl(photo) {
  if (!photo) return DEFAULT_AVATAR;
  if (/^(?:data:)/.test(photo)) return photo;
  // Re-base absolute /storage URLs onto the current origin so a mismatched
  // APP_URL host recorded at upload time cannot produce a broken <img>
  // (also heals values already persisted with the wrong host).
  const match = /^https?:\/\/[^/]+(\/storage\/.+)$/i.exec(photo);
  if (match) return match[1];
  if (/^(?:https?:)/.test(photo)) return photo;
  if (photo.startsWith('/')) return photo;
  return `/storage/${photo.replace(/^\/+/, "")}`;
}
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
  // Downscale + re-encode in the browser so uploads stay far below
  // shared-hosting PHP limits (InfinityFree caps post/upload size) and the
  // server's 2 MB file validation. Falls back to the original file if the
  // browser can't decode it (e.g. HEIC from phones).
  const resized = await resizeImage(file, 1600);
  const form = new FormData();
  form.append("photo", resized, "profile.jpg");
  const response = await fetch(`${apiBase}/photo`, {
    method: "POST",
    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken, "X-Requested-With": "XMLHttpRequest" },
    credentials: "same-origin",
    body: form,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const first = data.errors && Object.values(data.errors)[0];
    throw new Error((Array.isArray(first) ? first[0] : first) || data.message || data.error || `Upload failed (${response.status})`);
  }
  if (data.photo) {
    setProfilePhoto(data.photo);
  }
  return data;
}

function resizeImage(file, maxDim = 1600) {
  return new Promise((resolve) => {
    const objectUrl = (typeof URL !== "undefined" && URL.createObjectURL) ? URL.createObjectURL(file) : null;
    const finish = (value) => {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      resolve(value);
    };
    const img = new Image();
    img.onload = () => {
      try {
        const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(img.width * scale));
        canvas.height = Math.max(1, Math.round(img.height * scale));
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => finish(blob || file), "image/jpeg", 0.85);
      } catch (_) {
        finish(file);
      }
    };
    img.onerror = () => finish(file);
    if (objectUrl) {
      img.src = objectUrl;
    } else {
      const reader = new FileReader();
      reader.onload = () => {
        img.src = typeof reader.result === "string" ? reader.result : "";
      };
      reader.onerror = () => finish(file);
      reader.readAsDataURL(file);
    }
  });
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
async function uploadRequirementFile(file) {
  if (!file) throw new Error("No file selected");
  const form = new FormData();
  form.append("file", file);
  const response = await fetch(`${apiBase}/requirements/upload`, {
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
  document.querySelectorAll("[data-profile-photo]").forEach((img) => {
    img.src = getProfilePhoto(user);
    img.alt = `${user.firstName} ${user.lastName}`;
    img.onerror = () => {
      if (img.dataset.fallbackApplied !== "true") {
        img.dataset.fallbackApplied = "true";
        img.src = DEFAULT_AVATAR;
      }
    };
  });
  document.querySelectorAll("[data-user-name]").forEach((el) => { el.textContent = `${user.firstName} ${user.lastName}`; });
  document.querySelectorAll("[data-user-id]").forEach((el) => { el.textContent = user.id; });
}
window.DG = { saveData, getData, removeData, clearData, importData, pendingKeys, generateId, generateUserId, userIdExists, getCurrentUser, setCurrentUser, logoutUser, getProfilePhoto, normalizePhotoUrl, DEFAULT_AVATAR, setProfilePhoto, uploadProfilePhoto, uploadImage, uploadRequirementFile, loadProfileElements, hydrateFromBoot, flushSync, dedupeNotifications, STORAGE_KEYS };
