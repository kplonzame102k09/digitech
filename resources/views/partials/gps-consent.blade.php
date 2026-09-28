{{-- One-time location-consent ask for public pages (landing, login). Shows only when
     a secure context can actually grant geolocation and no choice was made yet.
     Reuses the portal's dg-gps-v1 key so a grant here carries into the authed
     portal, which then starts posting silently (no second prompt). No location
     is stored or posted from these pages -- there is no signed-in identity. --}}
<script>
    (() => {
        const KEY = "dg-gps-v1";
        const state = (() => {
            try { return JSON.parse(localStorage.getItem(KEY) || "{}"); } catch { return {}; }
        })();

        if (window.isSecureContext !== true) return; // geolocation needs HTTPS (or localhost)
        if (state.chosen) return;                     // already decided once per browser
        if (!navigator.geolocation) return;

        const banner = document.createElement("div");
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

        banner.querySelector("[data-gps-no]").addEventListener("click", () => {
            remember({ chosen: "denied" });
            banner.remove();
        });

        banner.querySelector("[data-gps-yes]").addEventListener("click", () => {
            banner.remove();
            // Fires the browser/OS permission prompt so the origin-level grant
            // is established now; the authed portal later posts from it.
            navigator.geolocation.getCurrentPosition(
                () => remember({ chosen: "granted", at: Date.now() }),
                () => remember({ chosen: "denied" }),
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    })();
</script>