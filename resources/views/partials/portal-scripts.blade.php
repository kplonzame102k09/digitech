@php
    $portalPage = $portalPage ?? null;
    $extraScripts = $extraScripts ?? [];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.__DIGITECH_BOOT__ = @json($portalBoot ?? ['csrf' => csrf_token(), 'collections' => [], 'currentUser' => null]);
</script>
<script>
    (() => {
        // GPS consent + capture + self-correction, no hardware, no extra
        // prompts. The live-monitoring map can only show a device-precise
        // "currently online" point when the user themselves says yes to the
        // location prompt — so we ask once, politely, and never pester after a
        // clear choice. Deny simply means the map falls back to IP geolocation
        // / home address as usual.
        //
        // Everything else reuses that one grant:
        //   - a continuous position watch while the tab is visible (with a
        //     simple throttle/move gate so posts stay within the rate limit),
        //   - a tiny rolling average so a stationary device's posted point
        //     tightens instead of jittering,
        //   - a hint when the OS only handed out an "approximate" (km-scale)
        //     grant, and
        //   - a "set my exact location" pin so users stuck on the IP/home
        //     fallback can correct their own marker (stored as _home_fix).
        const KEY = "dg-gps-v1";
        const state = (() => {
            try { return JSON.parse(localStorage.getItem(KEY) || "{}"); } catch { return {}; }
        })();

        const isSecure = window.isSecureContext === true;
        if (!isSecure) return; // geolocation needs HTTPS (or localhost)

        const csrf = () =>
            window.__DIGITECH_BOOT__?.csrf ||
            document.querySelector('meta[name="csrf-token"]')?.content ||
            "";

        const send = (lat, lon, accuracy) => {
            fetch("/api/portal/location", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
                body: JSON.stringify({ lat, lon, accuracy }),
            }).catch(() => { /* non-fatal; map falls back to IP geo */ });
        };

        const haversineMeters = (a, b) => {
            const rad = (d) => (d * Math.PI) / 180;
            const dLat = rad(b.lat - a.lat);
            const dLon = rad(b.lon - a.lon);
            const s = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLon / 2) ** 2;
            return 2 * 6371000 * Math.asin(Math.sqrt(s));
        };

        const COARSE_ACC = 500;         // OS "approximate" grants read at km scale
        const MOVE_GATE_M = 25;         // fixes closer than this count as "still"
        const POST_GATE_MS = 60 * 1000; // minimum spacing between posts
        const PRECISE_ACC = 150;        // fresh fixes better than this hide the pin
        let lastRaw = null;             // most recent device fix
        let lastSent = null;            // last posted point { lat, lon, at }
        let lastFixAcc = null;
        let lastFixAt = null;

        // Gate + smooth: posts are throttled to roughly one a minute and a
        // stable device has its last minute averaged into the previous fix so
        // the posted point tightens (noise reduction) instead of jittering.
        const decidePost = (lat, lon, acc) => {
            if (lastRaw && lastSent) {
                const dist = haversineMeters(lastRaw, { lat, lon });
                const since = Date.now() - (lastSent.at || 0);
                if (dist < MOVE_GATE_M && since < POST_GATE_MS) {
                    lastRaw = { lat, lon };
                    return; // throttled; keep the fresher fix for later gates
                }
                if (dist < MOVE_GATE_M) {
                    const mid = {
                        lat: (lastRaw.lat + lat) / 2,
                        lon: (lastRaw.lon + lon) / 2,
                        acc,
                    };
                    send(mid.lat, mid.lon, mid.acc);
                    lastSent = { lat: mid.lat, lon: mid.lon, at: Date.now() };
                    lastRaw = { lat, lon };
                    return;
                }
            }
            send(lat, lon, acc);
            lastSent = { lat, lon, at: Date.now() };
            lastRaw = { lat, lon };
        };

        // Coarse-grant hint: appears only while the device reports an
        // approximate (km-scale) position and disappears the moment a precise
        // fix arrives. Guidance text only — the OS Precise/Approximate slider
        // cannot be changed by a web page.
        const NUDGE_KEY = "dg-gps-coarse-v1";
        let nudgedOff = false;
        try { nudgedOff = localStorage.getItem(NUDGE_KEY) === "1"; } catch { /* ignore */ }
        let nudgeEl = null;
        const hideNudge = () => {
            if (nudgeEl) { nudgeEl.remove(); nudgeEl = null; }
        };
        const showNudge = () => {
            if (nudgedOff || nudgeEl) return;
            nudgeEl = document.createElement("div");
            nudgeEl.style.cssText = [
                "position:fixed", "z-index:9990", "bottom:84px", "right:16px",
                "max-width:340px", "background:#0f172a", "color:#e2e8f0",
                "padding:14px 16px", "border-radius:14px", "box-shadow:0 10px 30px rgba(0,0,0,.35)",
                "font:13px/1.5 system-ui,sans-serif", "display:flex", "flex-direction:column", "gap:8px",
            ].join(";");
            nudgeEl.innerHTML = `
                <p style="margin:0">Your device shared an <b>approximate</b> location (±km), so the live map can't pin you precisely.</p>
                <p style="margin:0">Enable <b>Precise</b> location for this site (browser or site settings → Location → Precise); it will pick up automatically.</p>
                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" data-coarse-ok style="cursor:pointer;border:none;background:#334155;color:#e2e8f0;border-radius:9px;padding:6px 12px">Got it</button>
                </div>`;
            nudgeEl.querySelector("[data-coarse-ok]").addEventListener("click", () => {
                try { localStorage.setItem(NUDGE_KEY, "1"); } catch { /* ignore */ }
                hideNudge();
            });
            document.body.appendChild(nudgeEl);
        };
        const updateNudge = (acc) => {
            if (Number.isFinite(acc) && acc >= COARSE_ACC) showNudge();
            else hideNudge();
        };

        // ---- self-set "I am actually here" pin (Phase 2) ----
        let calDialog = null;
        let calMap = null;
        let calMarker = null;
        let leafletPromise = null;

        const ensureLeaflet = () => {
            if (window.L && window.L.map && window.L.marker) return Promise.resolve();
            if (leafletPromise) return leafletPromise;
            leafletPromise = new Promise((resolve, reject) => {
                const css = document.createElement("link");
                css.rel = "stylesheet";
                css.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
                document.head.appendChild(css);
                const script = document.createElement("script");
                script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
                script.onload = () => resolve();
                script.onerror = () => reject(new Error("leaflet-unavailable"));
                document.head.appendChild(script);
            });
            return leafletPromise;
        };

        const initCalibrateDialog = () => {
            if (!calDialog) {
                calDialog = document.createElement("dialog");
                calDialog.id = "gps-calib-dialog";
                calDialog.style.cssText = [
                    "width:min(420px,calc(100vw - 2rem))", "border:0", "border-radius:16px",
                    "padding:0", "overflow:hidden", "box-shadow:0 20px 60px rgba(15,23,42,.35)",
                    "font:14px/1.5 system-ui,sans-serif",
                ].join(";");
                document.body.appendChild(calDialog);
                const darkNow = document.documentElement.classList.contains("dark");
                calDialog.classList.toggle("dark", darkNow);
                calDialog.style.background = darkNow ? "#0f172a" : "#ffffff";
                calDialog.style.color = darkNow ? "#e2e8f0" : "#0f172a";
                calDialog.innerHTML = `
                    <style>
                        #gps-calib-dialog::backdrop { background: rgba(15,23,42,.55); backdrop-filter: blur(4px); }
                        #gps-calib-dialog .gps-cal-top { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid #e2e8f0; }
                        #gps-calib-dialog.dark .gps-cal-top, #gps-calib-dialog.dark .gps-cal-foot { border-color:#334155; }
                        #gps-calib-dialog .gps-cal-title { margin:0; font-weight:700; }
                        #gps-calib-dialog .gps-cal-x { border:0; background:none; font-size:22px; line-height:1; cursor:pointer; color:#94a3b8; }
                        #gps-calib-map { height:280px; width:100%; }
                        #gps-calib-dialog .gps-cal-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border-top:1px solid #e2e8f0; }
                        #gps-calib-dialog .gps-cal-hint { margin:0; font-size:12px; color:#64748b; }
                        #gps-calib-dialog .gps-cal-save { border:0; border-radius:10px; background:#0d9488; color:#fff; font:600 14px system-ui,sans-serif; padding:8px 16px; cursor:pointer; }
                    </style>
                    <div class="gps-cal-top">
                        <p class="gps-cal-title">Set my exact location</p>
                        <button type="button" class="gps-cal-x" data-cal-close aria-label="Close">&times;</button>
                    </div>
                    <div id="gps-calib-map"></div>
                    <div class="gps-cal-foot">
                        <p class="gps-cal-hint">Drag the pin to where you actually are — used when GPS/IP can't find you.</p>
                        <button type="button" class="gps-cal-save" data-cal-save>Save pin</button>
                    </div>`;
                calDialog.querySelector("[data-cal-close]").addEventListener("click", () => calDialog.close());
                calDialog.addEventListener("click", (e) => {
                    const r = calDialog.getBoundingClientRect();
                    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                        calDialog.close();
                    }
                });
                calDialog.querySelector("[data-cal-save]").addEventListener("click", saveCalibrate);
            }

            const start = lastRaw || lastSent;
            const mapEl = calDialog.querySelector("#gps-calib-map");
            mapEl.innerHTML = "";
            if (calMap) { calMap.remove(); calMap = null; }
            const base = start ? { lat: start.lat, lon: start.lon } : { lat: 13.942473, lon: 121.623696 };
            calMap = L.map(mapEl, { zoomControl: true }).setView([base.lat, base.lon], start ? 17 : 13);
            L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: "&copy; OpenStreetMap contributors",
            }).addTo(calMap);
            calMarker = L.marker([base.lat, base.lon], { draggable: true }).addTo(calMap);
            if (!calDialog.open) calDialog.showModal();
            setTimeout(() => calMap && calMap.invalidateSize(), 0);
        };

        const saveCalibrate = () => {
            if (!calMarker) return;
            const pos = calMarker.getLatLng();
            fetch("/api/portal/self-location", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
                body: JSON.stringify({ lat: pos.lat, lon: pos.lng }),
            })
                .then(() => {
                    state.calDone = true;
                    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch { /* ignore */ }
                    document.querySelector("[data-calibrate]")?.remove();
                    calDialog?.close();
                })
                .catch(() => { /* non-fatal */ });
        };

        const calibrate = () => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.dataset.calibrate = "1";
            btn.title = "Set my exact location on the map";
            btn.setAttribute("aria-label", "Set my exact location on the map");
            btn.style.cssText = [
                "position:fixed", "z-index:9990", "bottom:84px", "left:16px",
                "display:flex", "align-items:center", "justify-content:center",
                "width:44px", "height:44px", "border-radius:14px", "border:1px solid #e2e8f0",
                "box-shadow:0 8px 24px rgba(15,23,42,.18)", "cursor:pointer", "color:#0d9488",
            ].join(";");
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;display:block"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
            const dismiss = document.createElement("span");
            dismiss.textContent = "×";
            dismiss.title = "Hide";
            dismiss.style.cssText = "position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:#f1f5f9;border:1px solid #e2e8f0;font:12px/16px system-ui,sans-serif;text-align:center;cursor:pointer;color:#64748b";
            dismiss.addEventListener("click", (e) => {
                e.stopPropagation();
                state.calDismissed = true;
                try { localStorage.setItem(KEY, JSON.stringify(state)); } catch { /* ignore */ }
                btn.remove();
            });
            btn.append(dismiss);
            btn.addEventListener("click", () => {
                ensureLeaflet().then(initCalibrateDialog).catch(() => {
                    state.calDismissed = true;
                    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch { /* ignore */ }
                });
            });
            const paint = () => {
                const dark = document.documentElement.classList.contains("dark");
                btn.style.background = dark ? "rgba(15,23,42,.9)" : "rgba(255,255,255,.9)";
                btn.style.borderColor = dark ? "#334155" : "#e2e8f0";
                dismiss.style.background = dark ? "#1e293b" : "#f1f5f9";
                dismiss.style.borderColor = dark ? "#334155" : "#e2e8f0";
                dismiss.style.color = dark ? "#94a3b8" : "#64748b";
            };
            paint();
            if (window.matchMedia) window.matchMedia("(prefers-color-scheme: dark)").addEventListener?.("change", paint);
            document.body.appendChild(btn);
        };

        const updateCalib = () => {
            const existing = document.querySelector("[data-calibrate]");
            if (state.calDismissed || state.calDone) { existing?.remove(); return; }
            const preciseNow =
                state.chosen === "granted" &&
                lastFixAcc != null && lastFixAcc < PRECISE_ACC &&
                lastFixAt != null && Date.now() - lastFixAt <= 60 * 60 * 1000;
            if (preciseNow) { existing?.remove(); return; }
            if (!existing) calibrate();
        };

        // ---- coalesce every fix source through one decision point ----
        const handleFix = (lat, lon, acc) => {
            lastFixAcc = acc;
            lastFixAt = Date.now();
            updateNudge(acc);
            updateCalib();
            decidePost(lat, lon, acc);
        };

        const fixOptions = { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 };

        let watchId = null;
        const startWatch = () => {
            if (!navigator.geolocation || watchId != null) return;
            try {
                watchId = navigator.geolocation.watchPosition(
                    (pos) => handleFix(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
                    () => {}, // transient failure — keep last known coords
                    fixOptions,
                );
            } catch { /* ignore */ }
        };
        const stopWatch = () => {
            if (watchId != null) {
                try { navigator.geolocation.clearWatch(watchId); } catch { /* ignore */ }
                watchId = null;
            }
        };
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "hidden") stopWatch();
            else { updateCalib(); startWatch(); }
        });

        // Previously approved: silently refresh on page load and every so often
        // as a fallback net when watchPosition never fires (browsers may
        // throttle it). The gates in decidePost keep this inside the rate limit.
        const GPS_REFRESH_MS = 5 * 60 * 1000;
        const refresh = () => {
            if (!navigator.geolocation || document.visibilityState !== "visible") return;
            try {
                navigator.geolocation.getCurrentPosition(
                    (pos) => handleFix(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
                    () => {}, // transient failure — keep last known coords
                    { ...fixOptions, maximumAge: 60000 },
                );
            } catch { /* ignore */ }
        };

        if (state.chosen === "granted") {
            refresh();
            setInterval(refresh, GPS_REFRESH_MS);
            startWatch();
            updateCalib();
            return;
        }

        if (state.chosen === "denied") {
            updateCalib();
            return;
        }

        // Show a small consent banner once per browser until a choice is made.
        const banner = document.createElement("div");
        banner.id = "gps-consent";
        banner.style.cssText = [
            "position:fixed", "z-index:9999", "bottom:16px", "right:16px",
            "max-width:340px", "background:#0f172a", "color:#e2e8f0",
            "padding:14px 16px", "border-radius:14px", "box-shadow:0 10px 30px rgba(0,0,0,.35)",
            "font:14px/1.5 system-ui,sans-serif", "display:flex", "flex-direction:column", "gap:10px",
        ].join(";");
        banner.innerHTML = `
            <p style="margin:0">Share your device location so the admin live map can show you with live precision? You can deny any time — the map still works without it.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button type="button" data-gps-no style="cursor:pointer;border:1px solid #475569;background:transparent;color:#cbd5e1;border-radius:9px;padding:6px 12px">Not now</button>
                <button type="button" data-gps-yes style="cursor:pointer;border:none;background:#16a34a;color:#fff;border-radius:9px;padding:6px 14px;font-weight:700">Share location</button>
            </div>`;
        document.body.appendChild(banner);

        const remember = (partial) => {
            try { localStorage.setItem(KEY, JSON.stringify({ ...state, ...partial })); } catch { /* ignore */ }
        };

        const deny = () => {
            remember({ chosen: "denied" });
            banner.remove();
            updateCalib();
        };

        const grant = () => {
            banner.remove();
            if (!navigator.geolocation) { remember({ chosen: "denied" }); updateCalib(); return; }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const { latitude, longitude, accuracy } = pos.coords;
                    remember({ chosen: "granted", at: Date.now() });
                    handleFix(latitude, longitude, accuracy);
                    startWatch();
                },
                () => { remember({ chosen: "denied" }); updateCalib(); }, // declined / blocked / timed out
                fixOptions,
            );
        };

        banner.querySelector("[data-gps-no]").addEventListener("click", deny);
        banner.querySelector("[data-gps-yes]").addEventListener("click", grant);
    })();
</script>
<script src="{{ asset('js/storage.js') . '?v=' . (file_exists(public_path('js/storage.js')) ? filemtime(public_path('js/storage.js')) : 1) }}"></script>
<script>
    if (window.DG && window.__DIGITECH_BOOT__) {
        DG.hydrateFromBoot(window.__DIGITECH_BOOT__);
    }
</script>
<script src="{{ asset('js/data.js') . '?v=' . (file_exists(public_path('js/data.js')) ? filemtime(public_path('js/data.js')) : 1) }}"></script>
<script src="{{ asset('js/auth.js') . '?v=' . (file_exists(public_path('js/auth.js')) ? filemtime(public_path('js/auth.js')) : 1) }}"></script>
<script src="{{ asset('js/app.js') . '?v=' . (file_exists(public_path('js/app.js')) ? filemtime(public_path('js/app.js')) : 1) }}"></script>
<script src="{{ asset('js/api.js') . '?v=' . (file_exists(public_path('js/api.js')) ? filemtime(public_path('js/api.js')) : 1) }}"></script>
<script src="{{ asset('js/features.js') . '?v=' . (file_exists(public_path('js/features.js')) ? filemtime(public_path('js/features.js')) : 1) }}"></script>
<script src="{{ asset('js/sync.js') . '?v=' . (file_exists(public_path('js/sync.js')) ? filemtime(public_path('js/sync.js')) : 1) }}"></script>
@foreach ($extraScripts as $script)
    <script src="{{ asset($script) . '?v=' . (file_exists(public_path($script)) ? filemtime(public_path($script)) : 1) }}"></script>
@endforeach
@if ($portalPage)
    @php($portalScriptPath = 'js/' . $portalPage)
    <script src="{{ asset($portalScriptPath) . '?v=' . (file_exists(public_path($portalScriptPath)) ? filemtime(public_path($portalScriptPath)) : 1) }}"></script>
@endif
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.DG) {
            DG.loadProfileElements();
        }
        if (window.APP && window.APP.autoClearBadges) {
            APP.autoClearBadges();
        }
        if (window.lucide) {
            lucide.createIcons();
        }
        if (window.APP && APP.applyTheme) {
            APP.applyTheme();
        }
        // Central theme toggle: capture-phase so exactly one toggle fires per
        // click even on pages whose role script also binds the button (or
        // doesn't bind it at all, e.g. live-monitoring / classroom pages).
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-theme-toggle]');
            if (!toggle) return;
            if (!window.APP || typeof APP.toggleTheme !== 'function') return;
            event.preventDefault();
            event.stopPropagation();
            APP.toggleTheme();
        }, true);
        const menuButton = document.getElementById('open');
        const sidebar = document.getElementById('side');
        if (menuButton && sidebar) {
            let backdrop = document.getElementById('sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'sidebar-backdrop';
                backdrop.setAttribute('aria-hidden', 'true');
                document.body.appendChild(backdrop);
            }

            const isMobile = () => window.innerWidth < 1024;

            const syncDrawer = () => {
                const open = isMobile() && !sidebar.classList.contains('-translate-x-full');
                backdrop.classList.toggle('open', open);
                document.documentElement.classList.toggle('drawer-open', open);
                menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            const closeDrawer = () => {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.remove('open');
                document.documentElement.classList.remove('drawer-open');
                menuButton.setAttribute('aria-expanded', 'false');
            };

            // Fallback toggle for roles that don't bind their own #open handler.
            // (Per-role scripts bind first and set data-menu-bound, so this
            // only runs where no other toggle exists -- no double-toggle.)
            if (!menuButton.dataset.menuBound) {
                menuButton.dataset.menuBound = '1';
                menuButton.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
            }

            // Decorate whichever toggle opened the drawer with a backdrop,
            // scroll lock, and Esc / outside-click / nav-click closing.
            document.addEventListener('click', (event) => {
                if (event.target.closest('#open')) {
                    setTimeout(syncDrawer, 0);
                } else if (event.target.closest('#sidebar-backdrop')) {
                    closeDrawer();
                } else if (event.target.closest('#side')) {
                    if (event.target.closest('a, button')) setTimeout(closeDrawer, 0);
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeDrawer();
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) closeDrawer();
            });

            menuButton.setAttribute('aria-expanded', 'false');
        }
        document.querySelectorAll('[data-logout]').forEach((button) => {
            if (button.dataset.boundLogout) return;
            button.dataset.boundLogout = '1';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                if (window.AUTH) AUTH.logout();
                else if (window.DG) DG.logoutUser();
            });
        });
    });
</script>
<script>
    const link = document.createElement('link');
    link.rel = 'icon';
    link.type = 'image/png';
    link.href = '/images/16432.png';
    document.head.appendChild(link);   
</script>
