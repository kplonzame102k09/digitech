@php
    $portalPage = $portalPage ?? null;
    $extraScripts = $extraScripts ?? [];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.__DIGITECH_BOOT__ = @json($portalBoot ?? ['csrf' => csrf_token(), 'collections' => [], 'currentUser' => null]);
</script>
<script src="{{ asset('js/storage.js') }}"></script>
<script>
    if (window.DG && window.__DIGITECH_BOOT__) {
        DG.hydrateFromBoot(window.__DIGITECH_BOOT__);
    }
</script>
<script src="{{ asset('js/data.js') }}"></script>
<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/api.js') }}"></script>
<script src="{{ asset('js/features.js') }}"></script>
@foreach ($extraScripts as $script)
    <script src="{{ asset($script) }}"></script>
@endforeach
@if ($portalPage)
    <script src="{{ asset('js/' . $portalPage) }}"></script>
@endif
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.DG) {
            DG.loadProfileElements();
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
