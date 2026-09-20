(function () {
  const u = window.AUTH ? AUTH.requireRole("admin") : null;
  if (!u) return;

  const API_BASE = "/admin/api/monitor/activity";
  const UNLOCK_URL = "/admin/api/monitor/unlock";
  const POLL_MS = 4000;
  const GEO_CACHE_KEY = "dg-ipgeo-v1";

  let rowsMap = new Map();
  let freshIds = new Set();
  let paused = false;
  let timerId = null;
  let currentDetail = null;
  let geoQueue = [];
  let geoBusy = false;
  let currentTopIps = [];
  let currentOnline = [];
  let mapInstance = null;
  let mapLayer = null;
  let lastMapKey = "";

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
    mapCard: document.getElementById("mapCard"),
    trafficMap: document.getElementById("trafficMap"),
    mapEmpty: document.getElementById("mapEmpty"),
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
      renderOnline(data.online || []);
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

    if (!freshIds.size && els.rows.children.length === rowsMap.size) {
      // ordering and contents unchanged — no need to touch the DOM
      return;
    }

    const tbody = els.rows;
    tbody.innerHTML = "";

    for (const row of rowsMap.values()) {
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

    els.emptyState.classList.toggle("hidden", rowsMap.size > 0);
    freshIds.clear();
  }

  function renderOnline(online) {
    els.onlineCount.textContent = online.length;
    currentOnline = Array.isArray(online) ? online : [];
    const empty = online.length === 0;
    els.onlineEmpty.classList.toggle("hidden", !empty);
    els.onlineList.innerHTML = "";

    for (const s of online) {
      const clone = els.onlineTemplate.content.cloneNode(true);
      clone.querySelector("[data-online-name]").textContent = s.name || "Unknown";
      clone.querySelector("[data-online-name]").title = s.ua || "";
      clone.querySelector("[data-online-role]").textContent = cap(s.role || "");
      clone.querySelector("[data-online-ip]").textContent = s.ip || "-";
      clone.querySelector("[data-online-ago]").textContent = timeAgo(s.ago);
      els.onlineList.appendChild(clone);
    }

    for (const s of online) {
      if (s.ip && !getCachedGeo(s.ip)) queueGeo(s.ip);
    }
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
        }
      } else {
        if (msgEl) {
          msgEl.textContent = (data && (data.error || data.message)) || "Failed to unlock.";
          msgEl.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
        }
      }
    } catch {
      if (msgEl) {
        msgEl.textContent = "An error occurred while unlocking.";
        msgEl.className = "mt-2 text-sm font-medium text-red-600 dark:text-red-400";
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
    const entry = geoCache[ip];
    return entry && entry.t ? entry.t : "";
  }

  function getGeoCoords(ip) {
    if (!ip) return null;
    const entry = geoCache[ip];
    if (!entry || !entry.lat || !entry.lon) return null;
    return { lat: entry.lat, lon: entry.lon, label: entry.t };
  }

  function queueGeo(ip) {
    if (!ip || geoQueue.includes(ip)) return;
    const entry = geoCache[ip];
    if (entry) {
      if (entry.lat != null && entry.lon != null) return; // fully resolved
      if (entry.t === "") return; // previously resolved to unknown — skip
      // legacy entry: has a label but no coords yet → re-resolve for lat/lon
    }
    geoQueue.push(ip);
  }

  function resolveGeoQueue() {
    if (geoBusy || !geoQueue.length) return;
    const ip = geoQueue.shift();
    geoBusy = true;
    fetch(`https://ipwho.is/${encodeURIComponent(ip)}`)
      .then((res) => res.json())
      .then((data) => {
        if (data && data.success && data.latitude != null && data.longitude != null) {
          const bits = [];
          if (data.city) bits.push(data.city);
          if (data.country_code) bits.push(data.country_code);
          geoCache[ip] = {
            t: bits.join(", "),
            lat: data.latitude,
            lon: data.longitude,
          };
        } else {
          geoCache[ip] = { t: "", lat: null, lon: null };
        }
        saveGeoCache(geoCache);
      })
      .catch(() => {
        geoCache[ip] = { t: "", lat: null, lon: null };
        saveGeoCache(geoCache);
      })
      .finally(() => {
        geoBusy = false;
        refreshGeoSpans(ip);
        renderMap();
        setTimeout(resolveGeoQueue, 800);
      });
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

  // ---- embedded traffic map (Leaflet + OpenStreetMap) ----

  function initMap() {
    if (mapInstance || !els.trafficMap) return;
    if (!window.L) {
      if (els.mapCard) els.mapCard.classList.add("hidden");
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
    if (!mapInstance) return;

    const seen = new Map();
    const add = (ip, hits) => {
      const geo = getGeoCoords(ip);
      if (!geo || seen.has(ip)) return;
      seen.set(ip, { ip, hits, geo });
    };

    for (const t of currentTopIps || []) add(t.ip, t.hits);
    for (const s of currentOnline || []) add(s.ip, 1);

    const points = Array.from(seen.values()).slice(0, 10);
    const key = points
      .map((p) => `${p.ip}:${p.geo.lat}:${p.geo.lon}:${p.hits}`)
      .join("|");

    if (key === lastMapKey) return;
    lastMapKey = key;

    if (els.mapCard) els.mapCard.classList.remove("hidden");
    mapLayer.clearLayers();

    if (!points.length) {
      if (els.mapEmpty) els.mapEmpty.classList.remove("hidden");
      return;
    }

    if (els.mapEmpty) els.mapEmpty.classList.add("hidden");

    for (const p of points) {
      const marker = L.marker([p.geo.lat, p.geo.lon]);
      marker.bindTooltip(
        `<b>${p.ip}</b><br/>${p.geo.label || "Unknown location"}<br/>${p.hits} hit${p.hits === 1 ? "" : "s"}`,
        { direction: "top" },
      );
      mapLayer.addLayer(marker);
    }

    if (points.length === 1) {
      mapInstance.setView([points[0].geo.lat, points[0].geo.lon], 10);
    } else {
      mapInstance.fitBounds(L.latLngBounds(points.map((p) => [p.geo.lat, p.geo.lon])), {
        padding: [24, 24],
        maxZoom: 10,
      });
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
      poll();
    });
  });

  let searchTimer = null;
  function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      rowsMap.clear();
      poll();
    }, 400);
  }

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

  const geoCache = loadGeoCache();

  start();
})();