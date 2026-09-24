@php
    $portalPage = $portalPage ?? null;
    $extraScripts = $extraScripts ?? [];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.__DIGITECH_BOOT__ = @json($portalBoot ?? ['csrf' => csrf_token(), 'collections' => [], 'currentUser' => null]);
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
