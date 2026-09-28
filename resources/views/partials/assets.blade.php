{{--
    Shared stylesheet/icon head for every portal page.

    Tailwind is compiled to a static stylesheet by `npm run build` instead of
    being compiled in the browser by the Play CDN. That removes the runtime
    Tailwind JavaScript from the critical path (it was the single largest
    render-blocking asset on every page) and makes the page render without a
    flash of unstyled content.

    The CDN is kept as a fallback because public/build is gitignored and the
    deploy scripts do not run `npm run build` yet. If the manifest is missing
    the page behaves exactly as it did before, so a deploy that forgets the
    build step cannot take the portal down. Once every environment builds the
    assets, the @else branch can be deleted.

    Lucide stays on the CDN but pinned: the pages call the global
    lucide.createIcons() from inline body scripts in 73 places, most of them
    without an `if (window.lucide)` guard, so the script has to stay a blocking
    classic script in <head>. Pinning stops icon renames in a future release
    from silently changing the UI.
--}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite('resources/css/app.css')
@else
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: "class" };
    </script>
@endif
<script src="https://unpkg.com/lucide@1.48.0"></script>
