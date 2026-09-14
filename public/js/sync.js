/**
 * Background sync: keeps every portal page fresh without manual reloads.
 *
 * Polls the existing GET /api/portal/{key} endpoints, merges changed
 * collections silently into memory (never PUTs back what it just read),
 * then broadcasts `digitech:sync` so pages re-render. Silent + idle-only:
 * pages skip re-rendering while a dialog is open or the user is typing,
 * and keys with unsent local writes are never overwritten mid-edit.
 */
(function () {
  const FAST_KEYS = [
    "notifications", "announcements", "attendance", "grades",
    "competencies", "enrollments", "requirements", "documentRequests",
    "parentLinkRequests", "settings",
  ];
  const SLOW_KEYS = ["users", "auditLogs"];
  const FAST_MS = 20000;
  const SLOW_MS = 120000;

  let running = false;
  let busy = false;

  const idle = () => {
    if (document.hidden) return false;
    if (document.querySelector("dialog[open]")) return false;
    const active = document.activeElement;
    if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return false;
    return true;
  };

  const fingerprint = (value) => {
    try {
      return JSON.stringify(value);
    } catch (_) {
      return null;
    }
  };

  async function fetchCollection(key) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const response = await fetch(`/api/portal/${encodeURIComponent(key)}`, {
      headers: { Accept: "application/json", "X-CSRF-TOKEN": token, "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
    });
    if (!response.ok) throw new Error(`Sync ${key}: ${response.status}`);
    const data = await response.json().catch(() => ({}));
    return data.value;
  }

  const unreadIds = () => {
    const current = (window.DG && DG.getCurrentUser && DG.getCurrentUser()) || null;
    if (!current) return new Set();
    return new Set(
      (DG.getData("notifications", []) || [])
        .filter((n) => n && n.userId === current.id && !n.read)
        .map((n) => n.id),
    );
  };

  async function pollKeys(keys) {
    if (busy || document.hidden || !window.DG || !DG.getCurrentUser()) return;
    busy = true;
    try {
      const skipped = new Set(DG.pendingKeys ? DG.pendingKeys() : []);
      const unreadBefore = unreadIds();
      const changed = [];
      for (const key of keys) {
        if (skipped.has(key)) continue; // unsent local edits win this round
        try {
          let value = await fetchCollection(key);
          if (key === "notifications" && Array.isArray(value) && DG.dedupeNotifications) {
            value = DG.dedupeNotifications(value);
          }
          if (fingerprint(DG.getData(key, null)) !== fingerprint(value)) {
            DG.importData(key, value);
            changed.push(key);
          }
        } catch (_) { /* per-key failure tolerated; retry next tick */ }
      }
      if (!changed.length) {
        // Heartbeat for REST-backed sections (classroom rosters, meetings):
        // no portal data changed, but listeners may still want a light refresh.
        document.dispatchEvent(new CustomEvent("digitech:tick", { detail: { keys: [] } }));
        return;
      }
      document.dispatchEvent(new CustomEvent("digitech:sync", { detail: { keys: changed } }));
      document.dispatchEvent(new CustomEvent("digitech:tick", { detail: { keys: changed } }));
      if (window.APP && typeof APP.updateNotif === "function") APP.updateNotif();
      if (changed.includes("notifications")) {
        const fresh = [...unreadIds()].filter((id) => !unreadBefore.has(id)).length;
        if (fresh > 0 && idle() && window.APP && typeof APP.toast === "function") {
          APP.toast(fresh === 1 ? "You have a new notification" : `You have ${fresh} new notifications`, "info");
        }
      }
    } finally {
      busy = false;
    }
  }

  function start() {
    if (running || !window.DG || !DG.getCurrentUser()) return;
    running = true;
    window.setInterval(() => pollKeys(FAST_KEYS), FAST_MS);
    window.setInterval(() => pollKeys(SLOW_KEYS), SLOW_MS);
    // Staggered first runs with jitter (avoid thundering herd after deploys).
    window.setTimeout(() => pollKeys(FAST_KEYS), 4000 + Math.floor(Math.random() * 4000));
    window.setTimeout(() => pollKeys(SLOW_KEYS), 15000 + Math.floor(Math.random() * 10000));
  }

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && running) pollKeys(FAST_KEYS);
  });
  if (window.__DIGITECH_READY__) start();
  else document.addEventListener("digitech:ready", start, { once: true });

  window.DG_SYNC = {
    start,
    idle,
    refresh: (keys) => pollKeys(keys || FAST_KEYS),
    FAST_KEYS,
    SLOW_KEYS,
  };
})();
