// Classroom message channel.
//
// Shared by the teacher and student classroom-detail pages. Both load this file
// directly (it is not a role page module, so it does not go through
// AUTH.requireRole) and it is a no-op when the page has no message panel.
//
// Delivery is polling. The target host has no cron, no queue worker and no
// long-lived processes, so there is no push channel available. The poll runs
// only while the Messages tab is active, the tab is visible, and the user is
// not mid-typing (reusing DG_SYNC.idle(), the same gate sync.js uses), so an
// open message box never competes with what the user is doing.
(() => {
  const panel = document.querySelector('[data-detail-panel="messages"]');
  if (!panel) return;

  const classroomId = document.body.dataset.classroomId;
  if (!classroomId) return;

  const $ = (sel) => panel.querySelector(sel);
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";
  // Every value is written with textContent, never innerHTML, so message
  // bodies and sender names cannot inject markup.

  const POLL_MS = 8000;
  const MAX_BODY = 2000;

  const list = $("[data-msg-list]");
  const emptyState = $("[data-msg-empty]");
  const form = $("[data-msg-form]");
  const input = $("[data-msg-input]");
  const fileInput = $("[data-msg-file]");
  const fileLabel = $("[data-msg-file-label]");
  const sendBtn = $("[data-msg-send]");
  const errorBox = $("[data-msg-error]");
  const olderBtn = $("[data-msg-older]");
  const tabButton = document.querySelector('[data-detail-tab="messages"]');
  // Two badges share this attribute: one on the tab itself, one in the panel
  // header. Both must move together, so query the document, not the panel.
  const badges = Array.from(document.querySelectorAll("[data-msg-badge]"));

  // Only a file the user has actually staged on this composer.
  let stagedFile = null;
  let sending = false;
  let pollTimer = null;
  let loaded = false;
  let loading = false;
  let loadingOlder = false;
  let hasMore = false;
  // Ids already on screen, so a poll that overlaps a just-sent message cannot
  // render it twice. A Set is used instead of a CSS attribute selector so no
  // escaping is needed and no CSS.escape support is assumed.
  const rendered = new Set();
  // Cursors. The server returns rows oldest-first, so the last element of a page
  // is the newest and the first is the oldest.
  let newestId = null;
  let oldestId = null;

  const setError = (message) => {
    if (!errorBox) return;
    errorBox.textContent = message || "";
    errorBox.classList.toggle("hidden", !message);
  };

  const toast = (message) => {
    if (window.APP && typeof APP.toast === "function") APP.toast(message);
  };

  async function api(url, options = {}) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf(),
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Request failed (${response.status})`);
    }
    return data;
  }

  const base = () => `/api/classrooms/${encodeURIComponent(DG.ref(classroomId))}/messages`;

  const isActive = () => tabButton && tabButton.getAttribute("aria-selected") === "true";

  const isIdle = () => {
    if (window.DG_SYNC && typeof DG_SYNC.idle === "function") return DG_SYNC.idle();
    if (document.hidden) return false;
    if (document.querySelector("dialog[open]")) return false;
    const el = document.activeElement;
    return !(el && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
  };

  const senderName = (m) => {
    if (!m.sender) return "Removed user";
    const name = `${m.sender.firstName ?? ""} ${m.sender.lastName ?? ""}`.trim();
    return name || "Unknown";
  };

  // The server marks own messages, so alignment does not depend on local
  // storage having been hydrated by the time this runs.
  const isOwn = (m) => !!m.mine;

  const initialsOf = (m) => {
    const s = m.sender;
    if (!s) return "?";
    const a = (s.firstName || "").trim().charAt(0);
    const b = (s.lastName || "").trim().charAt(0);
    return `${a}${b}`.toUpperCase() || "?";
  };

  const timeOf = (iso) => {
    if (!iso) return "";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return "";
    return d.toLocaleString(undefined, {
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
  };

  function renderMessage(m) {
  const mine = isOwn(m);

  const roleLabel =
    m.sender?.role === "teacher"
      ? "Teacher"
      : m.sender?.role === "admin"
        ? "Admin"
        : "";

  const wrap = document.createElement("div");
  wrap.className = `flex w-full min-w-0 gap-2 sm:gap-3 ${
    mine ? "flex-row-reverse" : ""
  }`;
  wrap.dataset.msgId = m.id;
  rendered.add(m.id);

  // Avatar
  const avatarWrap = document.createElement("div");
  avatarWrap.className = "shrink-0";

  const avatar = document.createElement("div");
  avatar.className =
    "flex h-7 w-7 items-center justify-center rounded-full " +
    "bg-slate-200 text-[11px] font-bold text-slate-600 " +
    "sm:h-8 sm:w-8 sm:text-xs " +
    "dark:bg-slate-700 dark:text-slate-200";

  avatar.textContent = initialsOf(m);
  avatarWrap.appendChild(avatar);

  // Message column
  const col = document.createElement("div");
  col.className =
    `flex min-w-0 max-w-[calc(100%-2.25rem)] flex-col sm:max-w-[85%] ${
      mine ? "items-end" : "items-start"
    }`;

  // Header
  const head = document.createElement("div");
  head.className =
    `mb-0.5 flex min-w-0 max-w-full flex-wrap items-center gap-x-2 gap-y-0.5 ` +
    `text-xs sm:mb-1 sm:gap-2 ${mine ? "justify-end" : ""}`;

  const who = document.createElement("span");
  who.className =
    "min-w-0 max-w-full truncate font-semibold text-slate-700 dark:text-slate-200";
  who.textContent = senderName(m);
  head.appendChild(who);

  if (roleLabel) {
    const tag = document.createElement("span");
    tag.className =
      "shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] " +
      "font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300";
    tag.textContent = roleLabel;
    head.appendChild(tag);
  }

  const when = document.createElement("span");
  when.className = "shrink-0 text-slate-400 dark:text-slate-500";
  when.textContent = timeOf(m.createdAt);
  head.appendChild(when);

  col.appendChild(head);

  // Message bubble
  if (m.body) {
    const body = document.createElement("p");

    body.className =
      "max-w-full min-w-0 rounded-2xl px-3 py-1.5 text-sm leading-relaxed " +
      "whitespace-pre-wrap break-words [overflow-wrap:anywhere] " +
      "sm:px-3.5 sm:py-2 " +
      (mine
        ? "bg-violet-600 text-white dark:bg-violet-500"
        : "bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100");

    body.textContent = m.body;
    col.appendChild(body);
  }

  // Attachment
  if (m.fileUrl) {
    const link = document.createElement("a");

    link.href = m.fileUrl;

    link.className =
      "mt-1 inline-flex max-w-full min-w-0 items-center gap-2 " +
      "rounded-2xl border border-slate-200 bg-white px-2.5 py-1.5 " +
      "text-xs font-medium text-slate-700 hover:bg-slate-50 " +
      "sm:px-3 sm:py-2 " +
      "dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 " +
      "dark:hover:bg-slate-700";

    const icon = document.createElement("i");
    icon.setAttribute("data-lucide", "paperclip");
    icon.className = "h-3.5 w-3.5 shrink-0";
    link.appendChild(icon);

    const name = document.createElement("span");
    name.className =
      "min-w-0 max-w-[12rem] truncate sm:max-w-[20rem]";
    name.textContent = m.fileName || "Attachment";

    link.appendChild(name);
    col.appendChild(link);
  }

  wrap.appendChild(avatarWrap);
  wrap.appendChild(col);

  return wrap;
}
  function setBadge(count) {
    const n = Number(count) || 0;
    badges.forEach((el) => {
      el.textContent = n > 99 ? "99+" : String(n);
      el.classList.toggle("hidden", n === 0);
    });
  }

  // Icons are inserted after the page-level Lucide pass has already run, so any
  // newly added <i data-lucide> has to be told to render. The pinned CDN script
  // is what provides window.lucide, and it is referenced only through `window`
  // so a missing or late script cannot raise a ReferenceError. Painting is
  // cosmetic, so it is also isolated: an icon failure must never abort a
  // render, because that would leave the thread drawn but not polling.
  function paintIcons(root) {
    try {
      const icon = window.lucide;
      if (!icon || typeof icon.createIcons !== "function") return;
      icon.createIcons({ nameAttr: "data-lucide", root: root || undefined });
    } catch (_) {
      // Ignore: the message itself is already in the DOM as text.
    }
  }

  function setOlderAvailable(available) {
    hasMore = !!available;
    if (!olderBtn) return;
    olderBtn.classList.toggle("hidden", !hasMore);
  }

  function renderAll(messages) {
    if (!list) return;
    list.textContent = "";
    rendered.clear();
    messages.forEach((m) => list.appendChild(renderMessage(m)));
    emptyState?.classList.toggle("hidden", messages.length > 0);
    paintIconsWhenReady(list);
  }

  // Appends a page of newer messages. The server already sends them
  // oldest-first, so they go on the end in order and the scroll only moves if
  // the reader was already at the bottom.
  function appendNewer(messages) {
    if (!list || !messages.length) return;
    const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
    messages.forEach((m) => {
      if (rendered.has(m.id)) return;
      list.appendChild(renderMessage(m));
    });
    paintIconsWhenReady(list);
    emptyState?.classList.toggle("hidden", list.children.length > 0);
    if (atBottom) list.scrollTop = list.scrollHeight;
  }

  // Prepends a page of older messages, preserving the reader's position by
  // restoring the distance from the bottom of the content.
  function prependOlder(messages) {
    if (!list || !messages.length) return;
    const before = list.scrollHeight - list.scrollTop;
    const fragment = document.createDocumentFragment();
    messages.forEach((m) => {
      if (rendered.has(m.id)) return;
      fragment.appendChild(renderMessage(m));
    });
    list.insertBefore(fragment, list.firstChild);
    paintIconsWhenReady(list);
    list.scrollTop = list.scrollHeight - before;
  }

  // Every page arrives oldest-first, so the last row is the newest and the
  // first is the oldest. Both ids double as the poll and history cursors.
  const trackPage = (messages) => {
    if (!messages || !messages.length) return;
    newestId = messages[messages.length - 1].id;
    oldestId = messages[0].id;
  };

  async function load() {
    if (loading) return;
    loading = true;
    setError("");
    try {
      const data = await api(base());
      const messages = Array.isArray(data.messages) ? data.messages : [];
      renderAll(messages);
      trackPage(messages);
      setOlderAvailable(data.hasMore);
      setBadge(data.unreadCount);
      if (list) list.scrollTop = list.scrollHeight;
      loaded = true;
    } catch (err) {
      setError(err.message || "Could not load messages.");
    } finally {
      loading = false;
    }
  }

  // Fetches just the badge. Runs once per page load so the tab shows a count
  // before it is ever opened, without paying for a page of messages.
  async function refreshBadge() {
    try {
      const data = await api(`${base()}?countOnly=1`);
      setBadge(data.unreadCount);
    } catch (_) {
      // Cosmetic only; the next activation or poll corrects it.
    }
  }

  async function loadOlder() {
    if (loadingOlder || !oldestId || !hasMore) return;
    loadingOlder = true;
    try {
      const data = await api(`${base()}?before=${encodeURIComponent(oldestId)}`);
      const messages = Array.isArray(data.messages) ? data.messages : [];
      prependOlder(messages);
      if (messages.length) oldestId = messages[0].id;
      setOlderAvailable(data.hasMore);
    } catch (err) {
      setError(err.message || "Could not load earlier messages.");
    } finally {
      loadingOlder = false;
    }
  }

  async function poll() {
    if (!loaded || sending || !isActive() || !isIdle()) return;
    try {
      // Without a cursor there is nothing to poll for; wait for a first load.
      if (!newestId) return;
      const data = await api(`${base()}?after=${encodeURIComponent(newestId)}`);
      const messages = Array.isArray(data.messages) ? data.messages : [];
      if (messages.length) {
        trackPage(messages);
        appendNewer(messages);
        // A poll only ever returns newer rows, so history is unchanged.
      }
      setBadge(data.unreadCount);
    } catch (_) {
      // Transient poll failures are silent by design: the next tick retries,
      // and toasting on a background poll would be noise.
    }
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(poll, POLL_MS);
  }

  async function markRead() {
    try {
      const data = await api(`${base()}/read`, { method: "POST", body: JSON.stringify({}) });
      setBadge(data.unreadCount);
    } catch (_) {
      // A missed read receipt only affects the badge; it self-corrects on the
      // next successful read or poll.
    }
  }

  function clearStagedFile() {
    stagedFile = null;
    if (fileInput) fileInput.value = "";
    if (fileLabel) {
      fileLabel.textContent = "Attach";
      fileLabel.classList.remove("text-violet-600", "dark:text-violet-300");
    }
  }

  async function uploadAttachment(file) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("classroom_id", classroomId);

    const response = await fetch("/api/classroom-files", {
      method: "POST",
      headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
      body: formData,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Upload failed (${response.status})`);
    }
    return data;
  }

  async function send() {
    if (sending) return;
    const body = (input?.value ?? "").trim();
    if (!body && !stagedFile) {
      setError("Write a message or attach a file first.");
      return;
    }
    if (body.length > MAX_BODY) {
      setError(`Message is too long (max ${MAX_BODY} characters).`);
      return;
    }

    sending = true;
    setError("");
    if (sendBtn) sendBtn.disabled = true;

    try {
      let fileUrl = null;
      let fileName = null;

      if (stagedFile) {
        const uploaded = await uploadAttachment(stagedFile);
        fileUrl = uploaded.fileUrl || null;
        fileName = stagedFile.name || null;
      }

      const payload = { body: body || null, fileUrl, fileName };
      const data = await api(base(), { method: "POST", body: JSON.stringify(payload) });

      if (data.message) {
        trackPage([data.message]);
        appendNewer([data.message]);
      }

      if (input) input.value = "";
      clearStagedFile();
      setBadge(0);
      if (list) list.scrollTop = list.scrollHeight;
    } catch (err) {
      setError(err.message || "Could not send the message.");
    } finally {
      sending = false;
      if (sendBtn) sendBtn.disabled = false;
    }
  }

  // The pinned Lucide script sits in <head> without defer, so it normally
  // parses before this file. If a cache ever serves the panel first, defer the
  // paint rather than dropping the icons for good.
  function paintIconsWhenReady(root) {
    paintIcons(root);
    if (window.lucide && typeof window.lucide.createIcons === "function") return;
    window.addEventListener(
      "load",
      () => paintIcons(root),
      { once: true },
    );
  }

  // The tab machinery in classroom-detail.js owns tab clicks, so the panel is
  // driven by observing the Messages tab's selected state instead of hooking
  // the same click handler.
  function syncWithTab() {
    if (!tabButton) return;
    if (isActive()) {
      if (!loaded) {
        // A no-op once loaded, so this cannot clobber a rendered page.
        load();
      } else {
        poll();
      }
      // Opening the tab is the read receipt. markRead() does not touch the
      // list, only the badge, so the freshly loaded messages stay visible.
      markRead();
      startPolling();
    } else {
      stopPolling();
    }
  }

  function wire() {
    form?.addEventListener("submit", (event) => {
      event.preventDefault();
      send();
    });

    // Enter sends, Shift+Enter inserts a newline.
    input?.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        send();
      }
    });

    fileInput?.addEventListener("change", () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) {
        clearStagedFile();
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        setError("Attachments must be 5 MB or smaller.");
        clearStagedFile();
        return;
      }
      stagedFile = file;
      if (fileLabel) {
        fileLabel.textContent = file.name;
        fileLabel.classList.add("text-violet-600", "dark:text-violet-300");
      }
      setError("");
    });

    // Pause the poll while the user is typing; the idle gate already covers
    // this, but stopping promptly avoids a wasted in-flight request.
    input?.addEventListener("focus", stopPolling);
    input?.addEventListener("blur", () => {
      if (isActive()) startPolling();
    });

    olderBtn?.addEventListener("click", loadOlder);

    if (tabButton) {
      new MutationObserver(syncWithTab).observe(tabButton, {
        attributes: true,
        attributeFilter: ["aria-selected"],
      });
    }

    // Coming back to the tab after being hidden should show current state.
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        stopPolling();
      } else if (isActive()) {
        poll();
        startPolling();
      }
    });
  }

  wire();
  // The tab badge is worth knowing before the tab is opened, so ask for the
  // count once on load even when the thread stays closed.
  refreshBadge();
  if (isActive()) {
    load();
    startPolling();
  }
})();
