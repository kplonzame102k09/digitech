(function () {
  const u = window.AUTH ? AUTH.requireRole("admin") : null;
  if (!u) return;

  const API_BASE = "/admin/api/monitor/activity";
  const UNLOCK_URL = "/admin/api/monitor/unlock";
  const LOCK_IP_URL = "/admin/api/monitor/lock-ip";
  const POLL_MS = 4000;
  const GEO_CACHE_KEY = "dg-ipgeo-v1";
  const GEO_CACHE_TTL = 7 * 24 * 60 * 60 * 1000; // re-resolve stale IPs weekly
  const CAMPUS_FALLBACK = { lat:13.942473, lon:121.623696, label: "Campus / Local network" };
  const CAMPUS_LAT = CAMPUS_FALLBACK.lat ?? CAMPUS_FALLBACK.latitude ?? 13.942473;
  const CAMPUS_LON = CAMPUS_FALLBACK.lon ?? CAMPUS_FALLBACK.longitude ?? 121.623696;

  let rowsMap = new Map();
  let freshIds = new Set();
  let paused = false;
  let timerId = null;
  let currentDetail = null;
  let geoQueue = [];
  let geoBusy = false;
  let currentTopIps = [];
  let currentOnline = [];
  let currentUsers = [];
  let mapInstance = null;
  let mapLayer = null;
  let lastMapKey = "";
  let pageSize = 10;
  let currentPage = 1;
  let lastRenderKey = "";

  const els = {
    pulse: document.getElementById("livePulse"),
    pauseBtn: document.getElementById("pauseBtn"),
    search: document.getElementById("search"),
    windowFilter: document.getElementById("windowFilter"),
    typeFilter: document.getElementById("typeFilter"),
    eventFilter: document.getElementById("eventFilter"),
    roleFilter: document.getElementById("roleFilter"),
    rows: document.getElementById("rows"),
    emptyState: document.getElementById("emptyState"),
    rowTemplate: document.getElementById("rowTemplate"),
    tablePagination: document.getElementById("tablePagination"),
    tableInfo: document.getElementById("tableInfo"),
    pageLabel: document.getElementById("pageLabel"),
    pagePrev: document.getElementById("pagePrev"),
    pageNext: document.getElementById("pageNext"),
    pageSize: document.getElementById("pageSize"),
    onlineList: document.getElementById("onlineList"),
    onlineEmpty: document.getElementById("onlineEmpty"),
    onlineCount: document.getElementById("onlineCount"),
    onlineTemplate: document.getElementById("onlineTemplate"),
    statOnlineNow: document.getElementById("statOnlineNow"),
    statActiveHour: document.getElementById("statActiveHour"),
    statRequests5m: document.getElementById("statRequests5m"),
    statSecurityToday: document.getElementById("statSecurityToday"),
    statTopIps: document.getElementById("statTopIps"),
    detailDialog: document.getElementById("detailDialog"),
    detailPath: document.getElementById("detailPath"),
    detailBody: document.getElementById("detailBody"),
    detailUnlock: document.getElementById("detailUnlock"),
    detailUnlockAccount: document.getElementById("detailUnlockAccount"),
    detailUnlockIp: document.getElementById("detailUnlockIp"),
    detailUnlockMsg: document.getElementById("detailUnlockMsg"),
    unlockIdentifier: document.getElementById("unlockIdentifier"),
    unlockRole: document.getElementById("unlockRole"),
    unlockIp: document.getElementById("unlockIp"),
    unlockBtn: document.getElementById("unlockBtn"),
    unlockMsg: document.getElementById("unlockMsg"),
    lockIpInput: document.getElementById("lockIpInput"),
    lockIpBtn: document.getElementById("lockIpBtn"),
    lockIpMsg: document.getElementById("lockIpMsg"),
    mapCard: document.getElementById("mapCard"),
    trafficMap: document.getElementById("trafficMap"),
    mapEmpty: document.getElementById("mapEmpty"),
    lockedAccountsList: document.getElementById("lockedAccountsList"),
    lockedEmpty: document.getElementById("lockedEmpty"),
    lockedCount: document.getElementById("lockedCount"),
    lockedTableContainer: document.getElementById("lockedTableContainer"),
    lockedAccountTemplate: document.getElementById("lockedAccountTemplate"),
  };

  const style = document.createElement("style");
  style.textContent =
    "@keyframes dgRowFlash{0%{background-color:rgba(74,222,128,.28)}100%{background-color:transparent}}" +
    "@keyframes dgRowFlashRed{0%{background-color:rgba(248,113,113,.32)}100%{background-color:transparent}}";
  document.head.appendChild(style);

  function esc(str) {
    if (str == null) return "";
    const s = String(str);
    return s
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function cap(str) {
    if (!str) return "";
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function timeAgo(secs) {
    if (secs == null) return "-";
    if (secs < 10) return "just now";
    if (secs < 60) return `${secs}s ago`;
    if (secs < 3600) return `${Math.floor(secs / 60)}m ago`;
    if (secs < 86400) return `${Math.floor(secs / 3600)}h ago`;
    return `${Math.floor(secs / 86400)}d ago`;
  }

  function clockTime(iso) {
    if (!iso) return "-";
    const d = new Date(iso);
    if (isNaN(d)) return "-";
    return d.toLocaleTimeString("en-PH", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  }

  function methodClass(method) {
    const m = (method || "").toUpperCase();
    if (m === "POST")
      return "text-green-600 dark:text-green-400";
    if (m === "PUT")
      return "text-yellow-600 dark:text-yellow-400";
    if (m === "PATCH")
      return "text-purple-600 dark:text-purple-400";
    if (m === "DELETE") return "text-red-600 dark:text-red-400";
    return "text-slate-500 dark:text-slate-400";
  }

  function statusClass(status) {
    const s = parseInt(status, 10);
    if (isNaN(s)) return "text-slate-500 dark:text-slate-400";
    if (s < 300) return "text-green-600 dark:text-green-400";
    if (s < 400) return "text-amber-600 dark:text-amber-400";
    if (s < 500) return "text-orange-600 dark:text-orange-400";
    return "text-red-600 dark:text-red-400";
  }

  function kindBadge(kind) {
    const map = {
      login_blocked:
        "inline-flex items-center rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-bold text-white",
      blocked:
        "inline-flex items-center rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-bold text-white",
      login_failed:
        "inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300",
      login_success:
        "inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-bold text-green-700 dark:bg-green-950/40 dark:text-green-300",
    };
    const labelMap = {
      login_blocked: "BLOCKED",
      blocked: "BLOCKED",
      login_failed: "FAILED LOGIN",
      login_success: "LOGIN",
    };
    const label = labelMap[kind] || "";
    const cls = map[kind];
    return cls ? `<span class="${cls}">${label}</span>` : "";
  }

  function isSecurityKind(kind) {
    return !!kind && kind !== "login_success";
  }

  function buildQuery() {
    const params = new URLSearchParams();
    const search = els.search.value.trim();
    if (search) params.append("search", search);
    params.append("window", els.windowFilter.value);
    if (els.typeFilter.value !== "all") params.append("type", els.typeFilter.value);
    if (els.eventFilter.value !== "all") params.append("event", els.eventFilter.value);
    if (els.roleFilter.value) params.append("role", els.roleFilter.value);
    return params.toString();
  }

  async function poll() {
    try {
      const res = await fetch(`${API_BASE}?${buildQuery()}`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.ok) return;

      renderActivity(data.activity || []);
      currentUsers = Array.isArray(data.users) ? data.users : [];
      renderOnline(data.online || []);
      renderLockedAccounts(data.lockedAccounts || []);
      renderStats(data.stats || {});
      resolveGeoQueue();
    } catch (err) {
      // transient network failure — the next poll will retry
    }
    renderMap();
  }

  function renderActivity(activity) {
    const seen = new Set();
    for (const row of activity) {
      seen.add(row.id);
      if (!rowsMap.has(row.id)) {
        rowsMap.set(row.id, row);
        freshIds.add(row.id);
      }
    }
    // drop rows that fell outside the current window
    for (const id of Array.from(rowsMap.keys())) {
      if (!seen.has(id)) rowsMap.delete(id);
    }

    renderTable();
    freshIds.clear();
  }

  function orderedRows() {
    return Array.from(rowsMap.values()).sort(
      (a, b) => (Number(b.id) || 0) - (Number(a.id) || 0),
    );
  }

  function renderTable() {
    const rows = orderedRows();
    const total = rows.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > pages) currentPage = pages;
    if (currentPage < 1) currentPage = 1;

    const start = Math.min((currentPage - 1) * pageSize, total);
    const pageRows = rows.slice(start, start + pageSize);

    const key = `${pageSize}:${currentPage}:${total}:${pageRows.map((r) => r.id).join(",")}`;
    if (key === lastRenderKey) return;
    lastRenderKey = key;

    const tbody = els.rows;
    tbody.innerHTML = "";

    for (const row of pageRows) {
      const clone = els.rowTemplate.content.cloneNode(true);
      const tr = clone.querySelector("tr");
      tr.dataset.id = row.id;

      clone.querySelector("[data-row-time]").textContent = clockTime(row.time);
      const actor = tr.querySelector("[data-row-actor]");
      actor.textContent = row.actor || "Guest";
      actor.className =
        "block font-medium" + (row.actor ? "" : " text-slate-400 font-normal");
      clone.querySelector("[data-row-role]").textContent = cap(row.role || "guest");
      clone.querySelector("[data-row-kind]").innerHTML = kindBadge(row.kind);
      clone.querySelector("[data-row-method]").textContent = row.method || "";
      clone.querySelector("[data-row-method]").className =
        "font-mono text-xs font-bold " + methodClass(row.method);
      clone.querySelector("[data-row-path]").textContent = row.path || "";
      clone.querySelector("[data-row-path]").title = row.path || "";

      const ipEl = clone.querySelector("[data-row-ip]");
      ipEl.textContent = row.ip || "-";
      const geoEl = clone.querySelector("[data-row-geo]");
      const geo = getCachedGeo(row.ip);
      if (geo) geoEl.textContent = geo;

      const statusEl = clone.querySelector("[data-row-status]");
      statusEl.textContent = row.status || "";
      statusEl.className = "font-mono text-xs font-bold " + statusClass(row.status);

      tr.addEventListener("click", () => openDetail(row.id));

      if (freshIds.has(row.id)) {
        tr.style.animation = isSecurityKind(row.kind)
          ? "dgRowFlashRed 2.5s ease"
          : "dgRowFlash 2.5s ease";
        tr.title = row.kind || "New activity";
      }

      tbody.appendChild(clone);
    }

    els.emptyState.classList.toggle("hidden", total > 0);
    els.tablePagination.classList.toggle("hidden", total === 0);

    els.tableInfo.textContent = total
      ? `Showing ${start + 1}–${start + pageRows.length} of ${total}`
      : "";
    els.pageLabel.textContent = `Page ${currentPage} of ${pages}`;
    els.pagePrev.disabled = currentPage <= 1;
    els.pageNext.disabled = currentPage >= pages;
  }

  function renderOnline(online) {
    els.onlineCount.textContent = online.length;
    currentOnline = Array.isArray(online) ? online : [];
    const empty = online.length === 0;
    els.onlineEmpty.classList.toggle("hidden", !empty);
    els.onlineList.innerHTML = "";

    // Which phone numbers belong to more than one account? Those can't be a
    // reliable identity key, so we flag them in the online list.
    const phoneCount = new Map();
    for (const u of currentUsers || []) {
      if (!u.phone) continue;
      phoneCount.set(u.phone, (phoneCount.get(u.phone) || 0) + 1);
    }

    for (const s of online) {
      const clone = els.onlineTemplate.content.cloneNode(true);
      clone.querySelector("[data-online-name]").textContent = s.name || "Unknown";
      clone.querySelector("[data-online-name]").title = s.ua || "";
      clone.querySelector("[data-online-id]").textContent = s.userId || "";
      clone.querySelector("[data-online-id]").classList.toggle("hidden", !s.userId);
      clone.querySelector("[data-online-role]").textContent = cap(s.role || "");
      clone.querySelector("[data-online-ip]").textContent = s.ip || "-";
      const phone = s.phone || "";
      clone.querySelector("[data-online-phone]").textContent = phone ? `phone ${phone}` : "no phone";
      clone.querySelector("[data-online-phone]").classList.toggle("text-slate-400", !phone);
      const dup = phone && (phoneCount.get(phone) || 0) > 1 ? "(dup)" : "";
      clone.querySelector("[data-online-dup]").textContent = dup;
      clone.querySelector("[data-online-dup]").classList.toggle("hidden", !dup);
      clone.querySelector("[data-online-ago]").textContent = timeAgo(s.ago);
      els.onlineList.appendChild(clone);
    }

    for (const s of online) {
      if (s.ip && !getCachedGeo(s.ip)) queueGeo(s.ip);
    }
  }

  let lockedAccounts = [];
  let countdownInterval = null;

  function renderLockedAccounts(accounts) {
    lockedAccounts = Array.isArray(accounts) ? accounts : [];
    els.lockedCount.textContent = lockedAccounts.length;
    const empty = lockedAccounts.length === 0;
    els.lockedEmpty.classList.toggle("hidden", !empty);
    els.lockedTableContainer.classList.toggle("hidden", empty);
    els.lockedAccountsList.innerHTML = "";

    for (const account of lockedAccounts) {
      const clone = els.lockedAccountTemplate.content.cloneNode(true);
      clone.querySelector("[data-locked-identifier]").textContent = account.identifier || "-";
      clone.querySelector("[data-locked-role]").textContent = account.role === "ip" ? "IP" : cap(account.role);

      const lockedAt = account.lockedAt ? new Date(account.lockedAt) : null;
      clone.querySelector("[data-locked-time]").textContent =
        lockedAt && !isNaN(lockedAt) ? lockedAt.toLocaleTimeString("en-PH") : "-";

      const countdownEl = clone.querySelector("[data-locked-countdown]");
      if (account.manual) {
        countdownEl.textContent = "Until unlocked";
        countdownEl.className = "font-mono text-xs font-bold text-slate-500 dark:text-slate-400";
      } else {
        countdownEl.dataset.unlockAt = account.unlockAt;
        countdownEl.textContent = formatCountdown(account.remainingSeconds);
      }

      const unlockBtn = clone.querySelector("[data-locked-unlock]");
      unlockBtn.addEventListener("click", () => unlockLockedAccount(account));

      els.lockedAccountsList.appendChild(clone);
    }

    // Start countdown timer if not already running
    if (lockedAccounts.length > 0 && !countdownInterval) {
      startCountdownTimer();
    } else if (lockedAccounts.length === 0 && countdownInterval) {
      stopCountdownTimer();
    }
  }

  function formatCountdown(seconds) {
    if (seconds <= 0) return "Expired";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
  }

  function startCountdownTimer() {
    if (countdownInterval) return;
    countdownInterval = setInterval(() => {
      const now = new Date().toISOString();
      let hasActive = false;

      document.querySelectorAll("[data-locked-countdown]").forEach((el) => {
        const unlockAt = el.dataset.unlockAt;
        if (!unlockAt) return;

        const unlockTime = new Date(unlockAt).getTime();
        const currentTime = new Date(now).getTime();
        const remaining = Math.max(0, Math.floor((unlockTime - currentTime) / 1000));

        if (remaining > 0) {
          hasActive = true;
          el.textContent = formatCountdown(remaining);
        } else {
          el.textContent = "Expired";
          el.classList.remove("text-red-600", "dark:text-red-400");
          el.classList.add("text-slate-400");
        }
      });

      if (!hasActive) {
        stopCountdownTimer();
        // Refresh to remove expired accounts
        poll();
      }
    }, 1000);
  }

  function stopCountdownTimer() {
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
  }

  function unlockLockedAccount(account) {
    const payload = {};
    if (account.role === 'ip') {
      payload.ip = account.identifier;
    } else {
      payload.identifier = account.identifier;
      payload.role = account.role;
    }

    unlockRequest(payload, null, null);
    // Refresh after unlock
    setTimeout(() => poll(), 500);
  }

  function renderStats(stats) {
    els.statOnlineNow.textContent = typeof stats.onlineNow === "number" ? stats.onlineNow : "-";
    els.statActiveHour.textContent = typeof stats.activeHour === "number" ? stats.activeHour : "-";
    els.statRequests5m.textContent = typeof stats.requests5m === "number" ? stats.requests5m : "-";
    els.statSecurityToday.textContent = typeof stats.securityToday === "number" ? stats.securityToday : "-";

    const list = els.statTopIps;
    list.innerHTML = "";
    const top = Array.isArray(stats.topIps) ? stats.topIps : [];
    currentTopIps = top;
    if (!top.length) {
      const li = document.createElement("li");
      li.className = "text-xs text-slate-400";
      li.textContent = "No hits tracked yet";
      list.appendChild(li);
      return;
    }
    for (const t of top) {
      const li = document.createElement("li");
      li.className = "flex items-center justify-between gap-2";
      const geo = getCachedGeo(t.ip);
      li.innerHTML = `<span class="truncate font-mono text-xs">${esc(t.ip)}${geo ? ` <span class="text-slate-400">${esc(geo)}</span>` : ""}</span><b class="text-xs">${t.hits}</b>`;
      list.appendChild(li);
      if (t.ip && !getCachedGeo(t.ip)) queueGeo(t.ip);
    }
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  async function unlockRequest(payload, msgEl, btn) {
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Unlocking…";
    }
    if (msgEl) {
      msgEl.textContent = "";
      msgEl.className = "mt-2 text-sm font-medium";
    }
    try {
      const res = await fetch(UNLOCK_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (data && data.ok) {
        const parts = [];
        if (data.identifier) parts.push(`account ${data.identifier} (${data.role})`);
        if (data.ip) parts.push(`IP ${data.ip}`);
        const cleared = Array.isArray(data.cleared) ? data.cleared.length : 0;
        if (msgEl) {
          msgEl.textContent = `Unlocked ${parts.join(" and ") || "target"}. Cleared ${cleared} lock key${cleared === 1 ? "" : "s"} — they can log in now.`;
          msgEl.className = "mt-2 text-sm font-medium text-green-700 dark:text-green-300";
        } else {
          // For locked accounts table unlock, show a toast
          APP?.toast?.(`Unlocked ${parts.join(" and ") || "target"}. Cleared ${cleared} lock key${cleared === 1 ? "" : "s"} — they can log in now.`);
        }
      } else {
        if (msgEl) {
          msgEl.textContent = (data && (data.error || data.message)) || "Failed to unlock.";
          msgEl.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
        } else {
          APP?.toast?.((data && (data.error || data.message)) || "Failed to unlock.", "error");
        }
      }
    } catch {
      if (msgEl) {
        msgEl.textContent = "An error occurred while unlocking.";
        msgEl.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
      } else {
        APP?.toast?.("An error occurred while unlocking.", "error");
      }
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = btn.dataset.label || "Unlock";
      }
    }
  }

  function openDetail(id) {
    const row = rowsMap.get(id);
    if (!row) return;
    currentDetail = row;
    els.detailPath.textContent = row.path || "-";
    const geo = getCachedGeo(row.ip);
    els.detailBody.innerHTML = `
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">User</span>
        <p class="font-medium">${esc(row.ip || "Guest")}</p>
      </div>
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">Role</span>
        <p class="font-medium">${esc(cap(row.role || "guest"))}</p>
      </div>
      ${row.target ? `
      <div class="sm:col-span-2">
        <span class="text-xs text-slate-500 dark:text-slate-400">Attempted target</span>
        <p class="font-mono font-medium">${esc(row.target)}${row.targetRole ? ` <span class="text-slate-400">(${esc(row.targetRole)})</span>` : ""}</p>
      </div>
      ` : ""}
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">Method</span>
        <p class="font-mono font-medium text-sm">${esc(row.method || "-")}</p>
      </div>
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">Status</span>
        <p class="font-mono font-medium">${esc(row.status || "-")}</p>
      </div>
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">IP Address</span>
        <p class="font-mono font-medium">${esc(row.ip || "-")}${geo ? ` <span class="text-slate-400">${esc(geo)}</span>` : ""}</p>
      </div>
      <div>
        <span class="text-xs text-slate-500 dark:text-slate-400">Time</span>
        <p class="font-medium">${esc(new Date(row.time).toLocaleString("en-PH"))}</p>
      </div>
      <div class="sm:col-span-2">
        <span class="text-xs text-slate-500 dark:text-slate-400">User Agent</span>
        <p class="mt-1 break-all text-xs leading-relaxed">${esc(row.ua || "-")}</p>
      </div>
    `;
    els.detailDialog.showModal();

    const security = isSecurityKind(row.kind);
    if (security && row.ip) {
      els.detailUnlockIp.style.display = "inline-flex";
      els.detailUnlockIp.dataset.label = "Unlock IP";
    } else {
      els.detailUnlockIp.style.display = "none";
    }

    if (security && row.target && row.targetRole) {
      els.detailUnlockAccount.style.display = "inline-flex";
      els.detailUnlockAccount.textContent = `Unlock ${row.target} (${row.targetRole})`;
      els.detailUnlockAccount.dataset.label = els.detailUnlockAccount.textContent;
    } else {
      els.detailUnlockAccount.style.display = "none";
    }

    els.detailUnlockMsg.textContent = "";
    els.detailUnlock.classList.toggle("hidden", !security);
  }

  // ---- geo lookup (client-side, ipwho.is, cached in localStorage) ----

  function loadGeoCache() {
    try {
      const raw = localStorage.getItem(GEO_CACHE_KEY);
      const parsed = raw ? JSON.parse(raw) : {};
      // migrate legacy string entries (label only) — coords resolve later
      for (const key of Object.keys(parsed)) {
        const v = parsed[key];
        if (typeof v === "string") parsed[key] = { t: v, lat: null, lon: null };
      }
      return parsed;
    } catch {
      return {};
    }
  }

  function saveGeoCache(cache) {
    try {
      localStorage.setItem(GEO_CACHE_KEY, JSON.stringify(cache));
    } catch {
      // storage full / unavailable — keep in-memory cache only
    }
  }

  function getCachedGeo(ip) {
    if (!ip) return "";
    if (isLocalIp(ip)) return CAMPUS_FALLBACK.label;
    const entry = geoCache[ip];
    return entry && entry.t ? entry.t : "";
  }

  function getGeoCoords(ip) {
    if (!ip) return null;
    if (isLocalIp(ip)) return { lat: CAMPUS_LAT, lon: CAMPUS_LON, label: CAMPUS_FALLBACK.label };
    const entry = geoCache[ip];
    if (!entry || !entry.lat || !entry.lon) return null;
    return { lat: entry.lat, lon: entry.lon, label: entry.t };
  }

  function isLocalIp(ip) {
    if (!ip) return true;
    const norm = ip.replace(/^::ffff:/, "");
    if (norm === "::1") return true;
    if (norm.includes(":")) return true; // any other IPv6 local/link-local/ULA
    const parts = norm.split(".").map(Number);
    if (parts.length !== 4) return true;
    const [a, b] = parts;
    return a === 10 || a === 127 || (a === 192 && b === 168) || (a === 172 && b >= 16 && b <= 31) || (a === 169 && b === 254) || a === 0;
  }

  function queueGeo(ip) {
    if (!ip || geoQueue.includes(ip)) return;

    // Private / local ranges can't be geolocated by ipwho.is — pin them to
    // the campus fallback point instead of waiting on (failing) lookups.
    if (isLocalIp(ip)) {
      geoCache[ip] = {
        t: CAMPUS_FALLBACK.label,
        lat: CAMPUS_LAT,
        lon: CAMPUS_LON,
        org: "",
        type: "local",
        resolvedAt: Date.now(),
      };
      saveGeoCache(geoCache);
      return;
    }

    const entry = geoCache[ip];
    if (entry) {
      if (entry.resolvedAt && Date.now() - entry.resolvedAt < GEO_CACHE_TTL) {
        return; // fresh enough — keep the cached result
      }
      if (entry.t === "" && !entry.resolvedAt) return; // previously unknown — skip legacy no-data entries
    }
    geoQueue.push(ip);
  }

  function resolveGeoQueue() {
    if (geoBusy || !geoQueue.length) return;
    const ip = geoQueue.shift();
    geoBusy = true;

    // Query both public IP geolocation APIs in parallel. They resolve to
    // ISP/city-level coordinates, not GPS; taking whichever reports the most
    // specific result gives us the closest approximation available client-side.
    const who = fetch(`https://ipwho.is/${encodeURIComponent(ip)}`)
      .then((res) => res.json())
      .then((data) => {
        if (data && data.success && data.latitude != null && data.longitude != null) {
          return {
            t: [data.city, data.country_code].filter(Boolean).join(", ") || "Unknown",
            lat: data.latitude,
            lon: data.longitude,
            org: data.connection?.org || "",
            type: data.connection?.type || "",
            resolvedAt: Date.now(),
          };
        }
        return null;
      })
      .catch(() => null);

    const api = fetch(`http://ip-api.com/json/${encodeURIComponent(ip)}`)
      .then((res) => res.json())
      .then((data) => {
        if (data && data.status === "success" && data.lat != null && data.lon != null) {
          return {
            t: [data.city, data.countryCode].filter(Boolean).join(", ") || "Unknown",
            lat: data.lat,
            lon: data.lon,
            org: data.org || "",
            type: data.mobile ? "mobile" : "",
            resolvedAt: Date.now(),
          };
        }
        return null;
      })
      .catch(() => null);

    Promise.allSettled([who, api])
      .then((results) => {
        const picks = results
          .filter((r) => r.status === "fulfilled" && r.value)
          .map((r) => r.value)
          .filter((g) => g.lat != null && g.lon != null);

        // Keep whichever side reported the more specific location (a named
        // city beats a bare country label) so the approximation is the best
        // either provider returned. Both "Unknown" cases clear the pending
        // state so we don't retry forever.
        picks.sort((a, b) => (nameSpecificity(b.t) - nameSpecificity(a.t)) || (a.resolvedAt - b.resolvedAt));
        const geo = picks[0] || null;
        if (geo) {
          geoCache[ip] = geo;
        } else {
          geoCache[ip] = { t: "", lat: null, lon: null };
        }
        saveGeoCache(geoCache);
      })
      .finally(() => {
        geoBusy = false;
        refreshGeoSpans(ip);
        renderMap();
        setTimeout(resolveGeoQueue, 800);
      });
  }

  function nameSpecificity(label) {
    // A multi-part label like "Iligan, PH" is a named city (specific);
    // bare country codes or empty labels are coarse. Higher = better.
    const s = String(label || "").trim();
    if (!s) return 0;
    if (s.includes(",")) return 3;
    if (s.length <= 3) return 1;
    return 2;
  }

  function refreshGeoSpans(ip) {
    const label = getCachedGeo(ip);
    document.querySelectorAll(`[data-row-ip="${CSS.escape(ip)}"]`).forEach((el) => {
      const geoEl = el.parentNode.querySelector("[data-row-geo]");
      if (geoEl) geoEl.textContent = label;
    });
    if (currentDetail?.ip === ip) {
      const ipNode = els.detailBody.querySelector("p.font-mono.font-medium");
      if (ipNode && label) ipNode.insertAdjacentText("beforeend", ` ${label}`);
    }
  }

  // ---- embedded online-users map (Leaflet + OpenStreetMap) ----

  function initMap() {
    if (mapInstance || !els.trafficMap) return;
    if (!window.L) {
      if (els.mapEmpty) {
        els.mapEmpty.classList.remove("hidden");
        els.mapEmpty.textContent =
          "Map library could not be loaded — online users are listed below instead.";
      }
      return;
    }
    mapInstance = L.map(els.trafficMap, {
      center: [13.0, 122.0],
      zoom: 5,
      zoomControl: true,
    });
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(mapInstance);
    mapLayer = L.layerGroup().addTo(mapInstance);
    requestAnimationFrame(() => mapInstance && mapInstance.invalidateSize());
  }

  function renderMap() {
    initMap();

    // Always keep the card visible; empty states are handled inside.
    if (els.mapCard) els.mapCard.classList.remove("hidden");

    if (!mapInstance) return;

    // Online users are pinned individually. Location source priority:
    // 1. GPS opt-in (device-level, most precise)
    // 2. IP geolocation (city/ISP level)
    // 3. registered address centroid (city -> province -> region, resolved
    //    server-side from the PSGC reference tables; barangay shown in label)
    // Offline users have no live signal and are grouped into a single campus
    // marker rather than misleading dots.
    const coordCount = new Map();
    const points = [];
    const offlineUsers = [];

    for (const u of currentUsers || []) {
      if (!u || !u.userId) continue;

      if (u.online && u.ip) {
        let geo = null;
        let source = "";

        if (u.gps && typeof u.gps.lat === "number" && typeof u.gps.lon === "number") {
          const gpsAt = new Date(u.gps.at || Date.now()).getTime();
          const gpsFresh = Number.isFinite(gpsAt) && Date.now() - gpsAt <= 24 * 60 * 60 * 1000;
          if (gpsFresh) {
            geo = { lat: u.gps.lat, lon: u.gps.lon, label: "GPS (device)" };
            source = "gps";
          }
        }
        if (!geo) {
          const ipGeo = getGeoCoords(u.ip);
          if (ipGeo) {
            geo = ipGeo;
            source = "ip";
          }
        }
        if (!geo && u.home && typeof u.home.lat === "number" && typeof u.home.lon === "number") {
          geo = { lat: u.home.lat, lon: u.home.lon, label: u.home.label || "Registered address (home)" };
          source = "home";
        }
        if (!geo) {
          geo = { lat: CAMPUS_LAT, lon: CAMPUS_LON, label: "Campus / Local network" };
          source = "campus";
        }

        const key = `${Math.round(geo.lat * 1000)}|${Math.round(geo.lon * 1000)}`;
        const n = (coordCount.get(key) || 0) + 1;
        coordCount.set(key, n);
        // Nudge stacked users so same-location markers don't overlap fully.
        const spread = Math.min(n - 1, 8) * 0.004;
        points.push({
          ...u,
          geo,
          source,
          lat: geo.lat + spread,
          lon: geo.lon + spread,
        });
        continue;
      }

      offlineUsers.push(u);
    }

    const key = points
      .map((p) => `${p.userId}:${p.source}:${Math.round(p.lat * 1000)}:${Math.round(p.lon * 1000)}:${p.ip || ""}:${p.phone || ""}`)
      .join("|") + `|offline:${offlineUsers.length}`;

    if (els.mapEmpty) {
      els.mapEmpty.classList.toggle("hidden", points.length > 0 || offlineUsers.length > 0);
      if (!points.length && !offlineUsers.length) {
        els.mapEmpty.textContent =
          "No users found for the selected role.";
      }
    }

    if (key === lastMapKey) return;
    lastMapKey = key;

    mapLayer.clearLayers();

    if (!points.length && !offlineUsers.length) return;

    for (const p of points) {
      const marker = L.marker([p.lat, p.lon]);
      const activity = `active ${timeAgo(p.ago)}`;
      const sourceBadge =
        p.source === "gps" ? '<span style="color:#7c3aed">GPS</span>'
        : p.source === "home" ? '<span style="color:#b45309">home</span>'
        : p.source === "campus" ? '<span style="color:#0ea5e9">campus</span>'
        : '<span style="color:#475569">IP</span>';
      marker.bindTooltip(
        `<b>${esc(p.name || "Unknown")}</b> <span style="color:#94a3b8">(${esc(p.userId || "")})</span><br/><span style="color:#16a34a">● ONLINE</span> · ${esc(p.role ? cap(p.role) : "")} · ${sourceBadge}<br/>phone ${esc(p.phone || "no phone")}${p.ip ? ` · ${esc(p.ip)}` : ""}<br/>${p.source === "home" && p.home && p.home.address ? "Registered: " + esc(p.home.address) : (p.geo.label ? esc(p.geo.label) : "Unknown location")}<br/>${activity}<br/>${p.geo.lat.toFixed(4)}, ${p.geo.lon.toFixed(4)}`,
        { direction: "top" },
      );
      mapLayer.addLayer(marker);
    }

    if (offlineUsers.length) {
      const count = offlineUsers.length;
      const roster = offlineUsers
        .map((u) => `${esc(u.name || u.userId || "Unknown")} — ${esc(u.role ? cap(u.role) : "?")} — ${esc(u.phone || "no phone")}`)
        .join("<br/>");
      const cluster = L.circleMarker([CAMPUS_LAT, CAMPUS_LON], {
        radius: 9 + Math.min(count, 12),
        color: "#94a3b8",
        fillColor: "#cbd5e1",
        fillOpacity: 0.7,
        weight: 1,
      });
      cluster.bindTooltip(
        `<b>${count} offline user${count === 1 ? "" : "s"}</b> — live location offline<br/>${roster}`,
        { direction: "top" },
      );
      mapLayer.addLayer(cluster);
    }

    requestAnimationFrame(() => mapInstance && mapInstance.invalidateSize());
  }

  // ---- polling lifecycle ----

  function start() {
    if (timerId) return;
    poll();
    timerId = setInterval(poll, POLL_MS);
  }

  function stop() {
    if (timerId) {
      clearInterval(timerId);
      timerId = null;
    }
  }

  function setPaused(value) {
    paused = value;
    if (paused) {
      stop();
      els.pulse.classList.add("opacity-50");
      els.pulse.querySelector("span.animate-ping")?.classList.remove("animate-ping");
      els.pauseBtn.textContent = "Resume";
      els.pulse.textContent = "PAUSED";
    } else {
      els.pulse.textContent = "";
      els.pulse.insertAdjacentHTML(
        "afterbegin",
        `<span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span></span>LIVE`,
      );
      els.pulse.classList.remove("opacity-50");
      els.pauseBtn.textContent = "Pause";
      start();
    }
  }

  function visibilityHandler() {
    if (document.hidden) stop();
    else if (!paused) start();
  }

  els.pauseBtn.addEventListener("click", () => setPaused(!paused));
  document.addEventListener("visibilitychange", visibilityHandler);

  els.search.addEventListener("input", () => scheduleSearch());
  ["windowFilter", "typeFilter", "eventFilter", "roleFilter"].forEach((id) => {
    document.getElementById(id).addEventListener("change", () => {
      rowsMap.clear();
      currentPage = 1;
      lastRenderKey = "";
      poll();
    });
  });

  let searchTimer = null;
  function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      rowsMap.clear();
      currentPage = 1;
      lastRenderKey = "";
      poll();
    }, 400);
  }

  els.pagePrev.addEventListener("click", () => {
    if (currentPage <= 1) return;
    currentPage--;
    lastRenderKey = "";
    renderTable();
  });

  els.pageNext.addEventListener("click", () => {
    currentPage++;
    lastRenderKey = "";
    renderTable();
  });

  els.pageSize.addEventListener("change", () => {
    pageSize = parseInt(els.pageSize.value, 10) || 20;
    currentPage = 1;
    lastRenderKey = "";
    renderTable();
  });

  document.querySelectorAll("[data-close-detail]").forEach((btn) => {
    btn.addEventListener("click", () => {
      els.detailDialog.close();
      currentDetail = null;
    });
  });

  els.detailUnlockAccount.addEventListener("click", () => {
    if (!currentDetail) return;
    unlockRequest(
      { identifier: currentDetail.target, role: currentDetail.targetRole },
      els.detailUnlockMsg,
      els.detailUnlockAccount,
    );
  });

  els.detailUnlockIp.addEventListener("click", () => {
    if (!currentDetail) return;
    unlockRequest({ ip: currentDetail.ip }, els.detailUnlockMsg, els.detailUnlockIp);
  });

  els.unlockBtn.addEventListener("click", () => {
    const payload = {};
    const id = els.unlockIdentifier.value.trim();
    if (id) {
      payload.identifier = id;
      payload.role = els.unlockRole.value;
    }
    const ip = els.unlockIp.value.trim();
    if (ip) payload.ip = ip;

    if (!id && !ip) {
      els.unlockMsg.textContent = "Enter a user ID / email and/or an IP address first.";
      els.unlockMsg.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
      return;
    }
    unlockRequest(payload, els.unlockMsg, els.unlockBtn);
  });

  els.lockIpBtn.addEventListener("click", () => lockIp());
  els.lockIpInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") lockIp();
  });

  async function lockIp() {
    const ip = els.lockIpInput.value.trim();
    els.lockIpMsg.textContent = "";
    els.lockIpMsg.className = "mt-2 text-sm font-medium";

    if (!ip) {
      els.lockIpMsg.textContent = "Enter an IP address to block.";
      els.lockIpMsg.className = "mt-2 text-sm font-medium text-amber-600 dark:text-amber-400";
      return;
    }
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
      els.lockIpMsg.textContent = "That does not look like a valid IPv4 address.";
      els.lockIpMsg.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
      return;
    }

    els.lockIpBtn.disabled = true;
    els.lockIpBtn.textContent = "Locking…";
    try {
      const res = await fetch(LOCK_IP_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ ip }),
      });
      const data = await res.json();
      if (data && data.ok) {
        els.lockIpMsg.textContent = `IP ${data.ip} is now blocked until you unlock it.`;
        els.lockIpMsg.className = "mt-2 text-sm font-medium text-green-700 dark:text-green-300";
        els.lockIpInput.value = "";
        APP?.toast?.(`IP ${data.ip} blocked`);
        poll();
      } else {
        els.lockIpMsg.textContent = (data && (data.error || data.message)) || "Failed to block the IP.";
        els.lockIpMsg.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
        APP?.toast?.("Failed to block IP", "error");
      }
    } catch {
      els.lockIpMsg.textContent = "An error occurred while blocking the IP.";
      els.lockIpMsg.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
      APP?.toast?.("Failed to block IP", "error");
    } finally {
      els.lockIpBtn.disabled = false;
      els.lockIpBtn.textContent = "Lock IP";
    }
  }

  const geoCache = loadGeoCache();

  start();
})();