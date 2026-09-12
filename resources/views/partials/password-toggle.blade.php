<script>
(function () {
    'use strict';

    var ICON_VISIBLE =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

    var ICON_HIDDEN =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>';

    function wrapPassword(input) {
        if (input.dataset.ptState) return;
        input.dataset.ptState = '1';

        var wrap = document.createElement('span');
        wrap.className = 'pt-wrap';
        wrap.style.cssText = 'position:relative;display:block;';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.style.paddingRight = '2.75rem';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.title = 'Show password';
        btn.setAttribute('aria-label', 'Show password');
        btn.style.cssText = 'position:absolute;top:50%;right:.5rem;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border:0;background:transparent;cursor:pointer;color:#94a3b8;';
        btn.innerHTML = ICON_VISIBLE;

        btn.addEventListener('click', function () {
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.innerHTML = reveal ? ICON_HIDDEN : ICON_VISIBLE;
            var label = reveal ? 'Hide password' : 'Show password';
            btn.setAttribute('aria-label', label);
            btn.title = label;
        });

        wrap.appendChild(btn);
    }

    function scan(root) {
        (root || document).querySelectorAll('input[type="password"]').forEach(wrapPassword);
    }

    function boot() {
        scan(document);

        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) scan(node);
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>