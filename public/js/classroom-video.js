/**
 * Meet-style video per classroom (self-hosted LiveKit, no recording).
 * Shared by teacher + student detail pages. Graceful when unconfigured:
 * shows a setup notice instead of breaking the page.
 *
 * Usage: window.ClassroomVideo.init({
 *   classroomId, meetingsUrl, tokenUrl, isTeacher,
 *   listEl, errorEl, overlayEl, gridEl, statusEl,
 * });
 */
(function () {
  const esc = (value) => (window.APP ? APP.esc(value ?? "") : String(value ?? ""));
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";
  const AVATAR_FALLBACK = "/images/16432.png";

  async function api(url, options = {}) {
    const response = await fetch(url, {
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || `Request failed (${response.status})`);
    }
    return data;
  }

  const fmtWhen = (iso) => {
    if (!iso) return "No time set";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString(undefined, { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
  };

  async function loadLiveKit() {
    // Pinned CDN ESM; dynamic import keeps pages working offline/unreachable.
    const urls = [
      "https://cdn.jsdelivr.net/npm/livekit-client@2/+esm",
      "https://unpkg.com/livekit-client@2/dist/livekit-client.esm.js",
    ];
    let lastError = null;
    for (const url of urls) {
      try {
        return await import(/* @vite-ignore */ url);
      } catch (error) {
        lastError = error;
      }
    }
    throw lastError || new Error("Video library failed to load.");
  }

  function getOrCreateTile(grid, label) {
    let tile = grid.querySelector(`[data-participant="${CSS.escape(label)}"]`);
    if (tile) return tile;
    tile = document.createElement("div");
    tile.className = "relative flex aspect-video items-center justify-center overflow-hidden rounded-2xl bg-slate-900 shadow-lg ring-emerald-500 transition-all duration-300";
    tile.dataset.participant = label;
    const tag = document.createElement("span");
    tag.className = "absolute bottom-2 left-2 z-10 rounded-full bg-black/60 px-2.5 py-1 text-[10px] font-bold text-white backdrop-blur-md";
    tag.textContent = label;
    tile.appendChild(tag);
    grid.appendChild(tile);
    return tile;
  }

  function setTileAvatar(tile, show) {
    if (!tile) return;
    tile.querySelectorAll("video").forEach((v) => v.classList.toggle("hidden", !!show));
    let img = tile.querySelector("[data-avatar]");
    if (show && !img) {
      img = document.createElement("img");
      img.dataset.avatar = "1";
      img.src = AVATAR_FALLBACK;
      img.alt = "Camera off";
      img.className = "absolute inset-0 m-auto h-20 w-20 rounded-full object-cover ring-2 ring-white/20";
      img.onerror = () => img.remove();
      tile.appendChild(img);
    } else if (!show && img) {
      img.remove();
    }
  }

  function tileFor(grid, participant) {
    // LiveKit tokens mint sub=identity (user_id) but name=full name, while the
    // local tile is labeled "{identity} (you)". Try every form so mute/unmute
    // always resolves — especially the current user's own tile.
    const base = participant.name || participant.identity;
    const candidates = [base, `${base} (you)`];
    if (participant.identity && participant.identity !== base) {
      candidates.push(participant.identity, `${participant.identity} (you)`);
    }
    for (const label of candidates) {
      const tile = grid.querySelector(`[data-participant="${CSS.escape(label)}"]`);
      if (tile) return tile;
    }
    return null;
  }

  function attachTrack(track, grid, label) {
    if (track.kind === "audio") {
      const el = track.attach();
      el.dataset.participant = label;
      document.body.appendChild(el); // Hidden audio element
      return null;
    }

    const tile = getOrCreateTile(grid, label);
    const el = track.attach();
    el.className = "absolute inset-0 h-full w-full object-cover";
    tile.appendChild(el);
    if (track.isMuted) setTileAvatar(tile, true); // joined with camera already off
    return tile;
  }

  function detachParticipant(grid, label) {
    grid.querySelectorAll(`[data-participant="${CSS.escape(label)}"]`).forEach((el) => el.remove());
    document.querySelectorAll(`audio[data-participant="${CSS.escape(label)}"]`).forEach((el) => el.remove());
  }

  const statusPill = (status) => {
    const s = String(status || "").toLowerCase();
    const color = s === "live"
      ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
      : s === "scheduled"
        ? "bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300"
        : "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400";
    return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ${color}">${s === "live" ? '<span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>' : ""}${esc(status || "—")}</span>`;
  };

  const meetingCard = (m, isTeacher) => `
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-violet-200 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-center">
      <div class="flex min-w-0 flex-1 items-start gap-3">
        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300">
          <i data-lucide="video" class="h-4 w-4"></i>
        </span>
        <div class="min-w-0 flex-1">
          <b class="block truncate">${esc(m.title)}</b>
          <p class="mt-0.5 text-xs text-slate-400">${esc(fmtWhen(m.starts_at))}${m.ends_at ? ` – ${esc(fmtWhen(m.ends_at))}` : ""}</p>
          <div class="mt-1.5">${statusPill(m.status)}</div>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        ${m.joinable ? `<button type="button" data-join-meeting="${esc(m.id)}" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-500"><i data-lucide="phone-incoming" class="h-3.5 w-3.5"></i>Join</button>` : ""}
        ${isTeacher && m.status === "scheduled" ? `<button type="button" data-start-meeting="${esc(m.id)}" class="inline-flex items-center gap-1.5 rounded-full bg-violet-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-violet-500"><i data-lucide="play" class="h-3.5 w-3.5"></i>Start</button>` : ""}
        ${isTeacher && m.status === "live" ? `<button type="button" data-end-meeting="${esc(m.id)}" class="rounded-full border border-amber-300 px-4 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300">End</button>` : ""}
        ${isTeacher ? `<button type="button" data-cancel-meeting="${esc(m.id)}" class="rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400">Cancel</button>
        <button type="button" data-delete-meeting="${esc(m.id)}" class="rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400">Delete</button>` : ""}
      </div>
    </div>`;
  async function init(options) {
    const {
      classroomId, meetingsUrl, tokenUrl, isTeacher,
      listEl, errorEl, overlayEl, gridEl, statusEl,
      joinInstantBtn, scheduleForm, historyEl,
      notifyIds, classroomName,
    } = options;

    const setError = (message) => {
      if (!errorEl) return;
      errorEl.textContent = message || "";
      errorEl.classList.toggle("hidden", !message);
    };

    let room = null;
    let livekit = null;
    let joinedMeetingId = null;
    let statusPoll = null;

    const stopStatusPoll = () => {
      if (statusPoll) {
        window.clearInterval(statusPoll);
        statusPoll = null;
      }
    };

    // While in a tracked meeting room, watch its status: when the teacher
    // ends/cancels/deletes it, everyone still inside leaves automatically.
    const startStatusPoll = () => {
      stopStatusPoll();
      if (!joinedMeetingId) return;
      statusPoll = window.setInterval(async () => {
        if (!room || !joinedMeetingId) {
          stopStatusPoll();
          return;
        }
        try {
          const data = await api(meetingsUrl);
          const current = (data.meetings || []).find((m) => String(m.id) === String(joinedMeetingId));
          const alive = current && ["scheduled", "live"].includes(String(current.status || "").toLowerCase());
          if (!alive) {
            stopStatusPoll();
            await leaveRoom();
            loadMeetings();
            if (window.APP && typeof window.APP.toast === "function") {
              window.APP.toast("The live session has ended.", "info");
            }
          }
        } catch (_) { /* transient failure; keep polling */ }
      }, 5000);
    };

    async function loadMeetings() {
      setError("");
      try {
        const data = await api(meetingsUrl);
        if (data.videoEnabled === false) {
          listEl.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Video conferencing is not set up yet. Ask your administrator to configure the LiveKit server (LIVEKIT_URL / KEY / SECRET).</div>`;
          if (joinInstantBtn) joinInstantBtn.disabled = true;
          return;
        }
        const meetings = data.meetings || [];
        const upcoming = meetings.filter((m) => ["scheduled", "live", "joinable"].includes(String(m.status || "").toLowerCase()) || m.joinable);
        const past = meetings.filter((m) => !upcoming.includes(m));
        listEl.innerHTML = upcoming.length
          ? upcoming.map((m) => meetingCard(m, isTeacher)).join("")
          : `<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">${isTeacher ? "No sessions yet — schedule the first one below." : "No upcoming sessions. Your teacher will post them here."}</div>`;
        renderHistory(past);
        bindMeetingButtons();
        if (window.lucide) lucide.createIcons();
      } catch (error) {
        setError(error.message || "Failed to load meetings.");
      }
    }

    function renderHistory(past) {
      const box = historyEl || (listEl ? document.getElementById(listEl.id.replace("Meetings", "History")) : null);
      if (!box) return;
      box.innerHTML = past.length
        ? past.map((m) => `
          <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-800/50">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200/70 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
              <i data-lucide="history" class="h-4 w-4"></i>
            </span>
            <div class="min-w-0 flex-1">
              <b class="block truncate text-sm">${esc(m.title)}</b>
              <p class="text-[11px] text-slate-400">${esc(fmtWhen(m.starts_at))}${m.ends_at ? ` – ${esc(fmtWhen(m.ends_at))}` : ""}</p>
            </div>
            ${statusPill(m.status)}
          </div>`).join("")
        : `<div class="rounded-2xl border border-dashed border-slate-300 p-5 text-center text-xs text-slate-400 dark:border-slate-700">No past sessions yet.</div>`;
      if (window.lucide) lucide.createIcons();
    }

    function bindMeetingButtons() {
      listEl.querySelectorAll("[data-join-meeting]").forEach((btn) =>
        btn.addEventListener("click", () => joinMeeting(btn.dataset.joinMeeting)),
      );
      if (!isTeacher) return;
      listEl.querySelectorAll("[data-start-meeting]").forEach((btn) =>
        btn.addEventListener("click", () => meetingAction(btn.dataset.startMeeting, "start")),
      );
      listEl.querySelectorAll("[data-end-meeting]").forEach((btn) =>
        btn.addEventListener("click", () => meetingAction(btn.dataset.endMeeting, "end")),
      );
      listEl.querySelectorAll("[data-cancel-meeting]").forEach((btn) =>
        btn.addEventListener("click", () => {
          if (!confirm("Cancel this session?")) return;
          meetingAction(btn.dataset.cancelMeeting, "cancel");
        }),
      );
      listEl.querySelectorAll("[data-delete-meeting]").forEach((btn) =>
        btn.addEventListener("click", async () => {
          if (!confirm("Delete this session?")) return;
          try {
            await api(`${meetingsUrl}/${encodeURIComponent(btn.dataset.deleteMeeting)}`, { method: "DELETE" });
            loadMeetings();
          } catch (error) {
            setError(error.message || "Delete failed.");
          }
        }),
      );
    }

    async function meetingAction(meetingId, action) {
      setError("");
      try {
        await api(`${meetingsUrl}/${encodeURIComponent(meetingId)}/${action}`, { method: "POST", body: JSON.stringify({}) });
        loadMeetings();
      } catch (error) {
        setError(error.message || "Action failed.");
      }
    }

    async function joinMeeting(meetingId) {
      await joinRoom(meetingId || null);
    }

    async function joinRoom(meetingId) {
      setError("");
      if (statusEl) statusEl.textContent = "Connecting…";
      try {
        const creds = await api(tokenUrl, {
          method: "POST",
          body: JSON.stringify(meetingId ? { meetingId } : {}),
        });
        livekit = await loadLiveKit();
        room = new livekit.Room({ adaptiveStream: true, dynacast: true });

        room.on(livekit.RoomEvent.TrackSubscribed, (track, _pub, participant) => {
          attachTrack(track, gridEl, participant.name || participant.identity);
        });

        room.on(livekit.RoomEvent.TrackMuted, (pub, participant) => {
          if (pub.kind !== "video" && pub.kind !== livekit.Track?.Kind?.Video) return;
          setTileAvatar(tileFor(gridEl, participant), true);
        });

        room.on(livekit.RoomEvent.TrackUnmuted, (pub, participant) => {
          if (pub.kind !== "video" && pub.kind !== livekit.Track?.Kind?.Video) return;
          setTileAvatar(tileFor(gridEl, participant), false);
        });

        room.on(livekit.RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
          track.detach().forEach((el) => el.remove());
          if (track.kind === "video") {
            const tile = gridEl.querySelector(`[data-participant="${CSS.escape(participant.name || participant.identity)}"]`);
            if (tile && !tile.querySelector("video")) tile.remove();
          }
        });

        room.on(livekit.RoomEvent.ActiveSpeakersChanged, (speakers) => {
          gridEl.querySelectorAll("[data-participant]").forEach((tile) => {
            const label = tile.dataset.participant;
            const isSpeaking = speakers.some((s) => (s.name || s.identity) === label || (label.includes("(you)") && s.isLocal));
            tile.classList.toggle("ring-2", isSpeaking);
          });
        });

        room.on(livekit.RoomEvent.ParticipantConnected, () => updateStatus());
        room.on(livekit.RoomEvent.ParticipantDisconnected, (participant) => {
          detachParticipant(gridEl, participant.name || participant.identity);
          updateStatus();
        });

        room.on(livekit.RoomEvent.Disconnected, () => {
          stopStatusPoll();
          joinedMeetingId = null;
          overlayEl?.classList.add("hidden");
          gridEl.innerHTML = "";
          document.querySelectorAll("audio[data-participant]").forEach((el) => el.remove());
        });

        await room.connect(creds.url, creds.token);
        // Mic/cam enabled individually so a denied/missing camera still
        // joins audio-only (tile shows the avatar fallback) instead of failing.
        try {
          await room.localParticipant.setMicrophoneEnabled(true);
        } catch (_) { /* audio unavailable; stay in room */ }
        try {
          await room.localParticipant.setCameraEnabled(true);
        } catch (_) { /* camera unavailable; avatar fallback shows */ }

        gridEl.innerHTML = "";
        const localLabel = `${room.localParticipant.identity} (you)`;
        room.localParticipant.trackPublications.forEach((pub) => {
          if (!pub.track) return;
          const tile = attachTrack(pub.track, gridEl, localLabel);
          if (tile && pub.kind === "video" && (pub.isMuted || pub.track.isMuted)) setTileAvatar(tile, true);
        });
        if (!room.localParticipant.isCameraEnabled) {
          // No local video publication (camera denied) — still show a tile with avatar.
          setTileAvatar(getOrCreateTile(gridEl, localLabel), true);
        }

        room.remoteParticipants.forEach((participant) => {
          participant.trackPublications.forEach((pub) => {
            if (!pub.track) return;
            const tile = attachTrack(pub.track, gridEl, participant.name || participant.identity);
            if (tile && pub.kind === "video" && (pub.isMuted || pub.track.isMuted)) setTileAvatar(tile, true);
          });
        });

        overlayEl?.classList.remove("hidden");
        overlayEl?.scrollIntoView({ behavior: "smooth", block: "start" });
        joinedMeetingId = meetingId || null;
        startStatusPoll();
        updateStatus(creds.room);
        if (window.lucide) lucide.createIcons();

        // Update control icons to match initial state
        updateControlUI();
      } catch (error) {
        if (statusEl) statusEl.textContent = "";
        setError(error.message || "Could not join the video room. Check your mic/cam permissions and connection.");
      }
    }

    function updateStatus(roomName) {
      if (!room) return;
      const count = room.remoteParticipants.size + 1;
      const label = `Live · ${count} participant${count === 1 ? "" : "s"}`;
      if (statusEl) {
        const r = roomName || statusEl.textContent.split("room ").pop();
        statusEl.textContent = `${label} · room ${r}`;
      }
      const countEl = overlayEl?.querySelector("[data-video-count]");
      if (countEl) countEl.textContent = label;
    }

    function updateControlUI() {
      if (!room) return;
      const micBtn = overlayEl?.querySelector("[data-video-mic]");
      const camBtn = overlayEl?.querySelector("[data-video-cam]");
      // lucide.createIcons() swaps <i> for <svg>, so match either.
      const micIcon = micBtn?.querySelector("i, svg");
      const camIcon = camBtn?.querySelector("i, svg");

      if (micBtn) {
        const enabled = room.localParticipant.isMicrophoneEnabled;
        micBtn.classList.toggle("bg-rose-600", !enabled);
        micBtn.classList.toggle("bg-white/10", enabled);
        if (micIcon) {
          micIcon.setAttribute("data-lucide", enabled ? "mic" : "mic-off");
        }
      }
      if (camBtn) {
        const enabled = room.localParticipant.isCameraEnabled;
        camBtn.classList.toggle("bg-rose-600", !enabled);
        camBtn.classList.toggle("bg-white/10", enabled);
        if (camIcon) {
          camIcon.setAttribute("data-lucide", enabled ? "video" : "video-off");
        }
      }
      if (window.lucide) lucide.createIcons();
    }

    async function leaveRoom() {
      stopStatusPoll();
      joinedMeetingId = null;
      try {
        await room?.disconnect();
      } catch (_) { /* noop */ }
      room = null;
      if (gridEl) gridEl.innerHTML = "";
      document.querySelectorAll("audio[data-participant]").forEach((el) => el.remove());
      overlayEl?.classList.add("hidden");
      if (statusEl) statusEl.textContent = "";
      const countEl = overlayEl?.querySelector("[data-video-count]");
      if (countEl) countEl.textContent = "";
    }

    // Teacher quick action: end the live session AND leave, so polling
    // participants drop automatically. Leave (above) only exits locally.
    async function endCall() {
      if (!isTeacher) return;
      setError("");
      const target = joinedMeetingId;
      try {
        if (target) {
          await api(`${meetingsUrl}/${encodeURIComponent(target)}/end`, { method: "POST", body: JSON.stringify({}) });
        }
      } catch (error) {
        setError(error.message || "Could not end the session.");
      }
      await leaveRoom();
      loadMeetings();
    }

    async function toggleMic() {
      if (!room) return;
      await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled);
      updateControlUI();
    }

    async function toggleCam() {
      if (!room) return;
      await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled);
      setTileAvatar(tileFor(gridEl, room.localParticipant), !room.localParticipant.isCameraEnabled);
      updateControlUI();
    }

    async function toggleShare(btn) {
      if (!room || !livekit) return;
      try {
        if (room.localParticipant.isScreenShareEnabled) {
          await room.localParticipant.setScreenShareEnabled(false);
          btn?.classList.remove("bg-emerald-600");
          btn?.classList.add("bg-white/10");
        } else {
          await room.localParticipant.setScreenShareEnabled(true);
          btn?.classList.add("bg-emerald-600");
          btn?.classList.remove("bg-white/10");
        }
      } catch (error) {
        setError(error.message || "Screen share failed.");
      }
    }

    async function startInstantMeeting() {
      if (!isTeacher || !joinInstantBtn || joinInstantBtn.disabled) return;
      setError("");
      joinInstantBtn.disabled = true;
      joinInstantBtn.classList.add("opacity-60");
      try {
        const created = await api(meetingsUrl, {
          method: "POST",
          body: JSON.stringify({ title: "Live class — now", starts_at: new Date().toISOString() }),
        });
        const meeting = created.meeting || {};
        if (meeting.id) {
          try {
            await api(`${meetingsUrl}/${encodeURIComponent(meeting.id)}/start`, { method: "POST", body: JSON.stringify({}) });
          } catch (startError) {
            setError(startError.message || "Meeting created but could not go live yet.");
          }
        }
        // Notify classroom students (same mechanism as grade/attendance notices).
        // Sources may be functions so the roster is read fresh at click time.
        try {
          const rawIds = typeof notifyIds === "function" ? notifyIds() : notifyIds;
          const name = typeof classroomName === "function" ? classroomName() : classroomName;
          const ids = [...new Set(rawIds || [])].filter(Boolean);
          if (ids.length && window.APP && typeof APP.notifyUsers === "function") {
            APP.notifyUsers(
              ids,
              "Live class started",
              `${name || "Your classroom"} is live now — open your classroom to join.`,
              "meeting",
              meeting.id || null,
            );
          }
        } catch (_) { /* notify is best-effort; the meeting still starts */ }
        loadMeetings();
        await joinMeeting(meeting.id || null);
      } catch (error) {
        setError(error.message || "Could not start the instant meeting.");
        loadMeetings();
      } finally {
        joinInstantBtn.disabled = false;
        joinInstantBtn.classList.remove("opacity-60");
      }
    }

    if (joinInstantBtn) {
      joinInstantBtn.addEventListener("click", () => {
        if (isTeacher) startInstantMeeting();
        else joinMeeting(null);
      });
    }
    scheduleForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      setError("");
      const title = scheduleForm.querySelector("[data-m-title]")?.value.trim() || "Live class";
      const starts = scheduleForm.querySelector("[data-m-starts]")?.value || null;
      const ends = scheduleForm.querySelector("[data-m-ends]")?.value || null;
      try {
        await api(meetingsUrl, { method: "POST", body: JSON.stringify({ title, starts_at: starts, ends_at: ends }) });
        scheduleForm.reset();
        loadMeetings();
      } catch (error) {
        setError(error.message || "Failed to schedule session.");
      }
    });

    const section = listEl?.closest("[data-video-section]");
    section?.querySelectorAll("[data-toggle-history]").forEach((btn) => {
      if (btn.dataset.historyBound) return;
      btn.dataset.historyBound = "1";
      btn.addEventListener("click", () => {
        const box = historyEl || (listEl ? document.getElementById(listEl.id.replace("Meetings", "History")) : null);
        if (!box) return;
        const hidden = box.classList.toggle("hidden");
        const label = btn.querySelector("[data-history-label]");
        if (label) label.textContent = hidden ? "Show" : "Hide";
      });
    });

    overlayEl?.querySelectorAll("[data-video-leave]").forEach((btn) =>
      btn.addEventListener("click", leaveRoom),
    );
    overlayEl?.querySelectorAll("[data-video-end-call]").forEach((btn) =>
      btn.addEventListener("click", endCall),
    );
    overlayEl?.querySelectorAll("[data-video-mic]").forEach((btn) =>
      btn.addEventListener("click", () => toggleMic(btn)),
    );
    overlayEl?.querySelectorAll("[data-video-cam]").forEach((btn) =>
      btn.addEventListener("click", () => toggleCam(btn)),
    );
    overlayEl?.querySelectorAll("[data-video-share]").forEach((btn) =>
      btn.addEventListener("click", () => toggleShare(btn)),
    );

    loadMeetings();

    return { reload: loadMeetings, leave: leaveRoom };
  }

  window.ClassroomVideo = { init };
})();
