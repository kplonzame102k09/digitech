<?php

namespace App\Http\Middleware;

use App\Support\UrlCipher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turn enciphered URL parameters back into the plaintext identifiers the rest
 * of the app already speaks.
 *
 * Runs at the very front of the web group so that every downstream consumer
 * -- route model binding, controllers, policies, and the closures that pass an
 * id straight into a view -- receives the identifier exactly as it did before
 * the cipher existed. That is what keeps this change non-breaking: not one
 * controller, policy, or query needs to know the cipher is there.
 *
 * Two values are treated differently on purpose:
 *
 *   "~..."  an enciphered token. It must decrypt or the request is refused
 *           with a 404. Silently falling back to a raw lookup would let an
 *           attacker strip the token off a URL they should not control and
 *           fall back to guessing plaintext ids.
 *   plain   a legacy identifier from a bookmark, chat message, or printed
 *           hand-out. Passed through untouched.
 */
class DecryptRouteParameters
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! UrlCipher::enabled()) {
            return $next($request);
        }

        $route = $request->route();

        // No matched route yet: let the router raise its own 404.
        if (! $route instanceof Route) {
            return $next($request);
        }

        $allowed = (array) config('portal.url_cipher.params', []);

        foreach ($route->parameters() as $name => $value) {
            if (! is_string($value) || $value === '' || ! in_array($name, $allowed, true)) {
                continue;
            }

            // A marked value that will not decrypt is a forged or corrupted
            // token, never a legacy link, so refuse it outright.
            $plain = UrlCipher::resolve($value);

            if ($plain === null) {
                abort(404);
            }

            if ($plain !== $value) {
                $route->setParameter($name, $plain);
            }
        }

        return $next($request);
    }
}
