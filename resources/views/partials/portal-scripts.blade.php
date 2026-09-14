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
