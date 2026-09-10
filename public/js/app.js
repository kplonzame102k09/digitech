(function () {
    function ensure() {
        let el = document.getElementById("global-loader");

        if (!el) {
            el = document.createElement("div");
            el.id = "global-loader";
            el.innerHTML = `
                <div class="loader-box">
                    <span class="spinner"></span>
                    <img src="/images/16432.png" alt="Loading" class="logo">
                </div>`;
            document.body.appendChild(el);
        }
        return el;
    }
    document.addEventListener("click", (e) => {
        const target = e.target.closest("a, button");
        if (!target) return;
        if (target.matches("#open, #menuBtn, #sidebar-backdrop")) {
            document.getElementById("global-loader")?.classList.remove("active");
            return;
        }
        const loader = ensure();
        loader.classList.add("active");

        if (  
            target.tagName === "A" &&
            target.href &&
            !target.target &&
            !target.getAttribute("onclick") &&
            !target.hasAttribute("download")
        ) return;

        setTimeout(() => loader.classList.remove("active"), 600);
    });
})();

function themeStorageKey(userId) {
  return `dg-theme-${String(userId || "guest").toLowerCase()}`;
}
function savedTheme() {
  try {
    const user = window.DG ? DG.getCurrentUser() : null;
    const perUser = localStorage.getItem(themeStorageKey(user?.id));
    if (perUser === "dark" || perUser === "light") return perUser;
    const last = localStorage.getItem("dg-theme-last");
    if (last === "dark" || last === "light") return last;
  } catch (_) {}
  return "";
}
function getTheme() {
  const saved = savedTheme();
  if (saved === "dark" || saved === "light") return saved;
  const settings = (window.DG ? DG.getData("settings", {}) : {}) || {};
  return settings.theme || "light";
}
function applyTheme() {
  const theme = getTheme();
  const isDark = theme === "dark";
  document.documentElement.classList.toggle("dark", isDark);
  document.documentElement.style.colorScheme = theme;
  updateThemeIcon(isDark);
}
function updateThemeIcon(isDark) {
  document
    .querySelectorAll("[data-theme-icon]")
    .forEach((icon) => {
      icon.setAttribute("data-lucide", isDark ? "sun" : "moon");
    });
  if (window.lucide) {
    lucide.createIcons();
  }
}
function toggleTheme() {
  const next = getTheme() === "dark" ? "light" : "dark";
  const user = window.DG ? DG.getCurrentUser() : null;
  try {
    localStorage.setItem(themeStorageKey(user?.id), next);
    localStorage.setItem("dg-theme-last", next);
  } catch (_) {}
  const settings = window.DG ? DG.getData("settings", {}) : {};
  if (settings && typeof settings === "object") {
    settings.theme = next;
    if (user?.role === "admin" && window.DG) {
      DG.saveData("settings", settings);
      if (DG.flushSync) {
        DG.flushSync();
      }
    }
  }
  applyTheme();
}
function setupThemeToggle() {
  const button = document.querySelector("[data-theme-toggle]");
  if (!button) return;
  button.addEventListener("click", toggleTheme);
}
if (window.DG && window.APP === undefined) {
  applyTheme();
}
function esc(v = "") {
  return String(v).replace(
    /[&<>"']/g,
    (c) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[c],
  );
}
function toast(message, type = "success") {
  const el = document.createElement("div");
  el.className =
    "toast fixed bottom-5 right-5 z-[100] max-w-sm rounded-xl px-4 py-3 text-sm font-medium shadow-xl " +
    (type === "error" ? "bg-red-600 text-white" : "bg-slate-900 text-white");
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 2800);
}
function initials(u) {
  return (
    `${u?.firstName?.[0] || ""}${u?.lastName?.[0] || ""}`.toUpperCase() || "DG"
  );
}
function roleLabel(r) {
  return r ? r[0].toUpperCase() + r.slice(1) : "";
}
function statusBadge(s) {
  const map = {
    Approved: "bg-emerald-50 text-emerald-700",
    Enrolled: "bg-emerald-50 text-emerald-700",
    Verified: "bg-emerald-50 text-emerald-700",
    Competent: "bg-emerald-50 text-emerald-700",
    Submitted: "bg-blue-50 text-blue-700",
    Processing: "bg-blue-50 text-blue-700",
    "Under Review": "bg-amber-50 text-amber-700",
    Pending: "bg-amber-50 text-amber-700",
    "In Progress": "bg-amber-50 text-amber-700",
    "Ready for Release": "bg-purple-50 text-purple-700",
    Released: "bg-purple-50 text-purple-700",
    Rejected: "bg-red-50 text-red-700",
    "Not Yet Competent": "bg-red-50 text-red-700",
    "Not Started": "bg-slate-100 text-slate-600",
    Draft: "bg-slate-100 text-slate-600",
  };
  return `<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${map[s] || "bg-slate-100 text-slate-600"}">${esc(s)}</span>`;
}
function formatDate(d) {
  if (!d) return "—";
  return new Date(d).toLocaleDateString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}
function updateNotif() {
  const u = DG.getCurrentUser();
  if (!u) return;
  const unread = DG.getData("notifications", []).filter(
    (x) => x.userId === u.id && !x.read,
  );
  const n = unread.length;
  const a =
      document.getElementById("notifCount") || document.getElementById("count"),
    b = document.getElementById("topNotif");
  if (a) a.textContent = n;
  if (b) {
    b.textContent = n;
    b.classList.toggle("hidden", n === 0);
  }
  document.querySelectorAll("[data-nav-notif]").forEach((el) => {
    const sources = (el.dataset.navNotif || "portal").split(" ").filter(Boolean);
    const count = sources.reduce((total, source) => {
      if (source === "parentLinkRequest" && u.role === "admin") {
        return total + DG.getData("parentLinkRequests", []).filter((r) => String(r.status || "").toLowerCase() === "pending").length;
      }
      return total + unread.filter((x) => (x.source || "portal") === source).length;
    }, 0);
    el.textContent = count;
    el.classList.toggle("hidden", count === 0);
  });
}
function notificationsEnabledFor(role) {
  if (!role) return true;
  const settings = DG.getData("settings", {});
  const key = `notify${role.charAt(0).toUpperCase()}${role.slice(1)}s`;
  return settings[key] !== false;
}
function notifyAdmins(title, message, source = "portal", recordId) {
  if (!notificationsEnabledFor("admin")) return [];
  const currentUser = DG.getCurrentUser();
  const users = DG.getData("users", []);
  const admins = users.filter((user) => user && user.role === "admin");

  // Keep the helper useful even when the current admin account has not yet
  // been persisted in the users list (for example, on a freshly seeded demo).
  if (!admins.length && currentUser?.role === "admin") admins.push(currentUser);
  if (!admins.length) return [];

  const now = new Date().toISOString();
  const notifications = DG.getData("notifications", []);
  const created = admins
    .filter((admin, index, all) => admin.id && all.findIndex((item) => item.id === admin.id) === index)
    .map((admin) => {
      const notification = {
        id: DG.generateId("NOT"),
        userId: admin.id,
        title: String(title || "Portal update"),
        message: String(message || "A new portal update is available."),
        date: now,
        read: false,
        source,
      };
      if (recordId) notification[`${source}Id`] = recordId;
      upsertNotification(notifications, notification, source, recordId);
      return notification;
    });

  DG.saveData("notifications", notifications);
  if (currentUser?.role === "admin") updateNotif();
  return created;
}

// Generic alias for pages that only need to create an admin-facing alert.
function createAdminNotification(title, message, source = "portal", recordId) {
  return notifyAdmins(title, message, source, recordId);
}

// Create notifications for an explicit list of user ids (any role).
function notifyUsers(userIds, title, message, source = "portal", recordId) {
  const users = DG.getData("users", []);
  const targets = [...new Set((userIds || []).filter(Boolean))].filter((userId) => {
    const target = users.find((user) => user.id === userId);
    return notificationsEnabledFor(target?.role);
  });
  const notifications = DG.getData("notifications", []);
  targets.forEach((userId) => {
    const notification = {
      id: DG.generateId("NOT"),
      userId,
      title: String(title || "Portal update"),
      message: String(message || ""),
      date: new Date().toISOString(),
      read: false,
      source,
    };
    if (recordId) notification[`${source}Id`] = recordId;
    upsertNotification(notifications, notification, source, recordId);
  });
  DG.saveData("notifications", notifications);
  const currentUser = DG.getCurrentUser();
  if (currentUser && targets.includes(currentUser.id)) updateNotif();
  return notifications;
}

function installNotificationDialog() {
  if (document.documentElement.dataset.notificationDialogInstalled) return;
  document.documentElement.dataset.notificationDialogInstalled = "true";
  document.addEventListener("click", (event) => {
    const trigger = event.target.closest?.(
      '[data-notifications], [data-show-notifications], [onclick*="showNotifications"]',
    );
    if (!trigger) return;
    event.preventDefault();
    showNotifications();
  });
}

function observeAdminNotificationEvents() {
  if (DG.__adminNotificationObserverInstalled) return;
  DG.__adminNotificationObserverInstalled = true;
  const saveData = DG.saveData;
  const watchedKeys = new Set([
    "enrollments",
    "requirements",
    "documentRequests",
    "announcements",
  ]);
  const recordId = (record) => record?.id || record?.studentId;
  const byId = (records) =>
    new Map((Array.isArray(records) ? records : []).map((record) => [recordId(record), record]));
  const studentName = (studentId) => {
    const student = DG.getData("users", []).find((user) => user.id === studentId);
    return `${student?.firstName || "Student"} ${student?.lastName || ""}`.trim();
  };

  DG.saveData = (key, value) => {
    if (!watchedKeys.has(key)) return saveData(key, value);
    const previous = DG.getData(key, []);
    const result = saveData(key, value);
    const currentUser = DG.getCurrentUser();
    if (!currentUser) return result;

    const before = byId(previous);
    const after = byId(value);
    after.forEach((record, id) => {
      const oldRecord = before.get(id);
      const isNew = !oldRecord;
      const wasSubmitted = oldRecord?.status === "Submitted";
      const isSubmitted = record.status === "Submitted";

      if (
        key === "enrollments" &&
        currentUser.role === "student" &&
        isSubmitted &&
        !wasSubmitted
      ) {
        notifyAdmins(
          "New enrollment submitted",
          `${studentName(record.studentId)} submitted enrollment ${record.id} for review.`,
          "enrollment",
          record.id,
        );
      }

      if (
        key === "requirements" &&
        currentUser.role === "student" &&
        isSubmitted &&
        !wasSubmitted
      ) {
        notifyAdmins(
          "Requirement submitted",
          `${studentName(record.studentId)} submitted ${record.name || "a requirement"} for review.`,
          "requirement",
          record.id,
        );
      }

      if (key === "documentRequests" && currentUser.role === "student" && isNew) {
        notifyAdmins(
          "New document request",
          `${studentName(record.studentId)} requested ${record.documentType || "a document"}.`,
          "document",
          record.id,
        );
      }

      if (
        key === "announcements" &&
        currentUser.role === "teacher" &&
        isNew
      ) {
        notifyAdmins(
          "Teacher announcement published",
          `${currentUser.firstName || "Teacher"} published “${record.title || "an announcement"}”.`,
          "announcement",
          record.id,
        );
      }
    });
    return result;
  };
}

observeAdminNotificationEvents();
installNotificationDialog();
// Insert or replace a notification so the same event never appears twice for
// the same user. Matches on (userId, source, recordId) when a record id is
// known; otherwise on (userId, source, title, message).
function upsertNotification(list, notification, source, recordId) {
  const index = list.findIndex(
    (existing) =>
      existing.userId === notification.userId &&
      (existing.source || "portal") === source &&
      (recordId
        ? existing[`${source}Id`] === recordId
        : existing.title === notification.title && existing.message === notification.message),
  );
  if (index >= 0) list[index] = notification;
  else list.push(notification);
}

function markSourceRead(source) {
  const u = DG.getCurrentUser();
  if (!u || !source) return;
  const notifications = DG.getData("notifications", []);
  let changed = false;
  notifications.forEach((notification) => {
    if (
      notification.userId === u.id &&
      (notification.source || "portal") === source &&
      !notification.read
    ) {
      notification.read = true;
      changed = true;
    }
  });
  if (changed) {
    DG.saveData("notifications", notifications);
    updateNotif();
  }
}

const PAGE_SOURCE_MAP = {
  "/student/enrollment": ["enrollment"],
  "/student/requirements": ["requirement"],
  "/student/documents": ["document"],
  "/student/grades": ["grade"],
  "/student/competencies": ["competency"],
  "/student/attendance": ["attendance"],
  "/student/announcements": ["announcement"],
  "/teacher/grades": ["grade"],
  "/teacher/competencies": ["competency"],
  "/teacher/attendance": ["attendance"],
  "/teacher/announcements": ["announcement"],
  "/parent/grades": ["grade"],
  "/parent/attendance": ["attendance"],
  "/parent/documents": ["document"],
  "/parent/announcements": ["announcement"],
  "/admin/enrollment": ["enrollment"],
  "/admin/documents": ["document"],
  "/admin/requirements": ["requirement"],
  "/admin/announcements": ["announcement"],
  "/admin/parent-links": ["parentLinkRequest"],
  "/guest/documents": ["document"],
  "/guest/announcements": ["announcement"],
};

function autoClearBadges() {
  (PAGE_SOURCE_MAP[location.pathname] || []).forEach(markSourceRead);
}

function showNotifications() {
  const u = DG.getCurrentUser();
  if (!u) return;
  const seen = new Set();
  const ns = DG.getData("notifications", [])
    .filter((n) => n.userId === u.id)
    .sort((a, b) => new Date(b.date) - new Date(a.date))
    .filter((n) => {
      const key =
        n.id ||
        `${n.source || "portal"}|${n[`${n.source}Id`] || ""}|${n.title}|${n.message}`;
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  const root = document.getElementById("modalRoot");
  if (!root) return;
  root.replaceChildren();

  const backdrop = document.createElement("div");
  backdrop.className =
    "modal-backdrop fixed inset-0 z-[90] flex items-center justify-center p-4";
  const modal = document.createElement("div");
  modal.className =
    "w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900";
  const header = document.createElement("div");
  header.className = "flex items-center justify-between";
  const heading = document.createElement("h3");
  heading.className = "text-lg font-bold";
  heading.textContent = "Notifications";
  const close = document.createElement("button");
  close.type = "button";
  close.className = "rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800";
  close.setAttribute("aria-label", "Close notifications");
  close.addEventListener("click", closeModal);
  const closeIcon = document.createElement("i");
  closeIcon.dataset.lucide = "x";
  closeIcon.className = "h-5 w-5";
  close.append(closeIcon);
  header.append(heading, close);

  const list = document.createElement("div");
  list.className = "mt-4 max-h-[55vh] space-y-2 overflow-auto";
  if (ns.length) {
    ns.forEach((notification) => {
      const item = document.createElement("div");
      item.className = `rounded-xl border p-3 ${notification.read ? "border-slate-200" : "border-green-200 bg-green-50/60"} dark:border-slate-700 dark:bg-slate-800`;
      const meta = document.createElement("div");
      meta.className = "flex justify-between gap-3";
      const title = document.createElement("p");
      title.className = "text-sm font-semibold";
      title.textContent = notification.title || "Portal update";
      const when = document.createElement("span");
      when.className = "text-[11px] text-slate-400";
      when.textContent = formatDate(notification.date);
      meta.append(title, when);
      const message = document.createElement("p");
      message.className = "mt-1 text-sm text-slate-500";
      message.textContent = notification.message || "No additional details.";
      item.append(meta, message);
      list.append(item);
    });
  } else {
    const empty = document.createElement("div");
    empty.className = "py-10 text-center text-sm text-slate-400";
    empty.textContent = "No notifications yet.";
    list.append(empty);
  }

  const markRead = document.createElement("button");
  markRead.type = "button";
  markRead.className =
    "mt-4 w-full rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700";
  markRead.textContent = "Mark all as read";
  markRead.addEventListener("click", markNotificationsRead);
  modal.append(header, list, markRead);
  backdrop.append(modal);
  root.append(backdrop);
  window.lucide?.createIcons?.();
}
function markNotificationsRead() {
  const u = DG.getCurrentUser();
  const ns = DG.getData("notifications", []);
  ns.forEach((n) => {
    if (n.userId === u.id) n.read = true;
  });
  DG.saveData("notifications", ns);
  closeModal();
  updateNotif();
  toast("Notifications marked as read");
}
function closeModal() {
  document.getElementById("modalRoot")?.replaceChildren();
}
window.APP = {
  applyTheme,
  toggleTheme,
  toast,
  esc,
  initials,
  statusBadge,
  formatDate,
  showNotifications,
  markNotificationsRead,
  closeModal,
  updateNotif,
  notifyAdmins,
  createAdminNotification,
  notifyUsers,
  notificationsEnabledFor,
  markSourceRead,
  autoClearBadges,
  generateId: DG.generateId,
};