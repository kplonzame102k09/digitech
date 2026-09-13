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

  function attachTrack(track, grid, label) {
    if (track.kind === "audio") {
      const el = track.attach();
      el.dataset.participant = label;
      document.body.appendChild(el); // Hidden audio element
      return null;
    }

    let tile = grid.querySelector(`[data-participant="${CSS.escape(label)}"]`);
    if (!tile) {
      tile = document.createElement("div");
      tile.className = "relative overflow-hidden rounded-2xl bg-slate-800 aspect-video shadow-lg ring-emerald-500 transition-all duration-300";
      tile.dataset.participant = label;
      const tag = document.createElement("span");
      tag.className = "absolute bottom-2 left-2 z-10 rounded-lg bg-black/60 px-2.5 py-1 text-[10px] font-bold text-white backdrop-blur-md";
      tag.textContent = label;
      tile.appendChild(tag);
      grid.appendChild(tile);
    }

    const el = track.attach();
    el.className = "h-full w-full object-cover";
    tile.appendChild(el);
    return tile;
  }

  function detachParticipant(grid, label) {
    grid.querySelectorAll(`[data-participant="${CSS.escape(label)}"]`).forEach((el) => el.remove());
    document.querySelectorAll(`audio[data-participant="${CSS.escape(label)}"]`).forEach((el) => el.remove());
  }

  async function init(options) {
    const {
      classroomId, meetingsUrl, tokenUrl, isTeacher,
      listEl, errorEl, overlayEl, gridEl, statusEl,
      joinInstantBtn, scheduleForm,
    } = options;

    const setError = (message) => {
      if (!errorEl) return;
      errorEl.textContent = message || "";
      errorEl.classList.toggle("hidden", !message);
    };

    let room = null;
    let livekit = null;

    async function loadMeetings() {
      setError("");
      try {
        const data = await api(meetingsUrl);
        if (data.videoEnabled === false) {
          listEl.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">Video conferencing is not set up yet. Ask your administrator to configure the LiveKit server (LIVEKIT_URL / KEY / SECRET).</div>`;
          if (joinInstantBtn) joinInstantBtn.disabled = true;
          return;
        }
        const meetings = data.meetings || [];
        listEl.innerHTML = meetings.length
          ? meetings.map((m) => `
            <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 p-4 dark:border-slate-700 sm:flex-row sm:items-center">
              <div class="min-w-0 flex-1">
                <b class="block truncate">${esc(m.title)}</b>
                <p class="mt-0.5 text-xs text-slate-400">${esc(fmtWhen(m.starts_at))}${m.ends_at ? ` – ${esc(fmtWhen(m.ends_at))}` : ""} · <span class="font-semibold">${esc(m.status)}</span></p>
              </div>
              <div class="flex flex-wrap gap-2">
                ${m.joinable ? `<button type="button" data-join-meeting="${esc(m.id)}" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Join</button>` : ""}
                ${isTeacher && m.status === "scheduled" ? `<button type="button" data-start-meeting="${esc(m.id)}" class="rounded-xl border px-3 py-2 text-xs font-semibold">Start</button>` : ""}
                ${isTeacher && m.status === "live" ? `<button type="button" data-end-meeting="${esc(m.id)}" class="rounded-xl border px-3 py-2 text-xs font-semibold">End</button>` : ""}
                ${isTeacher ? `<button type="button" data-cancel-meeting="${esc(m.id)}" class="rounded-xl border px-3 py-2 text-xs font-semibold text-slate-500">Cancel</button>
                <button type="button" data-delete-meeting="${esc(m.id)}" class="rounded-xl border px-3 py-2 text-xs font-semibold text-rose-600">Delete</button>` : ""}
              </div>
            </div>`).join("")
          : `<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">${isTeacher ? "No sessions yet — schedule the first one below." : "No upcoming sessions. Your teacher will post them here."}</div>`;
        bindMeetingButtons();
      } catch (error) {
        setError(error.message || "Failed to load meetings.");
      }
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
          overlayEl?.classList.add("hidden");
          gridEl.innerHTML = "";
          document.querySelectorAll("audio[data-participant]").forEach((el) => el.remove());
        });

        await room.connect(creds.url, creds.token);
        await room.localParticipant.setMicrophoneEnabled(true);
        await room.localParticipant.setCameraEnabled(true);

        gridEl.innerHTML = "";
        room.localParticipant.trackPublications.forEach((pub) => {
          if (pub.track) attachTrack(pub.track, gridEl, `${room.localParticipant.identity} (you)`);
        });

        room.remoteParticipants.forEach((participant) => {
          participant.trackPublications.forEach((pub) => {
            if (pub.track) attachTrack(pub.track, gridEl, participant.name || participant.identity);
          });
        });

        overlayEl?.classList.remove("hidden");
        overlayEl?.scrollIntoView({ behavior: "smooth", block: "start" });
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
      if (!statusEl || !room) return;
      const count = room.remoteParticipants.size + 1;
      const r = roomName || statusEl.textContent.split("room ").pop();
      statusEl.textContent = `Live · ${count} participant${count === 1 ? "" : "s"} · room ${r}`;
    }

    function updateControlUI() {
      if (!room) return;
      const micBtn = overlayEl?.querySelector("[data-video-mic]");
      const camBtn = overlayEl?.querySelector("[data-video-cam]");
      const micIcon = micBtn?.querySelector("i");
      const camIcon = camBtn?.querySelector("i");

      if (micBtn) {
        const enabled = room.localParticipant.isMicrophoneEnabled;
        micBtn.classList.toggle("bg-rose-600", !enabled);
        micBtn.classList.toggle("text-white", !enabled);
        micBtn.classList.toggle("border-rose-600", !enabled);
        if (micIcon) {
          micIcon.setAttribute("data-lucide", enabled ? "mic" : "mic-off");
        }
      }
      if (camBtn) {
        const enabled = room.localParticipant.isCameraEnabled;
        camBtn.classList.toggle("bg-rose-600", !enabled);
        camBtn.classList.toggle("text-white", !enabled);
        camBtn.classList.toggle("border-rose-600", !enabled);
        if (camIcon) {
          camIcon.setAttribute("data-lucide", enabled ? "video" : "video-off");
        }
      }
      if (window.lucide) lucide.createIcons();
    }

    async function leaveRoom() {
      try {
        await room?.disconnect();
      } catch (_) { /* noop */ }
      room = null;
      if (gridEl) gridEl.innerHTML = "";
      document.querySelectorAll("audio[data-participant]").forEach((el) => el.remove());
      overlayEl?.classList.add("hidden");
      if (statusEl) statusEl.textContent = "";
    }

    async function toggleMic() {
      if (!room) return;
      await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled);
      updateControlUI();
    }

    async function toggleCam() {
      if (!room) return;
      await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled);
      updateControlUI();
    }

    async function toggleShare(btn) {
      if (!room || !livekit) return;
      try {
        if (room.localParticipant.isScreenShareEnabled) {
          await room.localParticipant.setScreenShareEnabled(false);
          btn?.classList.remove("bg-emerald-600", "text-white", "border-emerald-600");
        } else {
          await room.localParticipant.setScreenShareEnabled(true);
          btn?.classList.add("bg-emerald-600", "text-white", "border-emerald-600");
        }
      } catch (error) {
        setError(error.message || "Screen share failed.");
      }
    }

    if (joinInstantBtn) {
      joinInstantBtn.addEventListener("click", () => joinMeeting(null));
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

    overlayEl?.querySelectorAll("[data-video-leave]").forEach((btn) =>
      btn.addEventListener("click", leaveRoom),
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
