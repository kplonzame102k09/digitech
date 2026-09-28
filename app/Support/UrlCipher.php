<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Opaque, tamper-evident tokens for identifiers that travel in URL paths and
 * query strings.
 *
 * Encrypts with the application key (AES-256-CBC via Laravel's Crypt) so a
 * reader cannot tell a sequential account-request id or a `STU-2026-000123`
 * user id from any other, and cannot mint a token for an id they were not
 * given. This is defence in depth: every controller already scopes its lookup
 * by owner before consulting a policy, so a guessed id yields 404 rather than
 * data. What it removes is the id itself from browser history, Referer headers,
 * proxy and server access logs, and screenshots.
 *
 * Two properties make this safe to roll out without breaking live links:
 *
 *  1. Every ciphertext is prefixed with MARKER. A value without the marker is
 *     a legacy plaintext identifier and is passed through untouched, so old
 *     bookmarks, chat messages and printed hand-outs keep working.
 *  2. Decryption failures return null instead of throwing, letting the caller
 *     decide between "treat as plaintext" and "404". A corrupted or forged
 *     token is never silently downgraded to a raw lookup.
 *
 * Encryption is deliberately NOT deterministic: Crypt::encryptString picks a
 * fresh IV per call, so the same id yields a different token each time. That
 * keeps the cipher semantically secure and stops two records with the same id
 * from producing identical tokens in a page. Every emitted token still
 * decrypts to the right id, so a client may hold any of them.
 *
 * Only URL parameters are ever enciphered. Identifiers inside JSON request
 * bodies stay plaintext because the sync write path is column-whitelisted by
 * WRITE_FIELDS; see PortalDataService::scopedDomainFields().
 */
final class UrlCipher
{
    /**
     * Prefix that marks a value as ciphertext. Chosen because it cannot begin
     * any identifier the app mints (all start with a letter or digit) and is
     * legal in a path segment and a query value once percent-encoded.
     */
    public const MARKER = '~';

    /**
     * Encrypt a single identifier into a URL-safe opaque token.
     *
     * Empty input stays empty so callers can pass nullable ids straight
     * through without producing a token that decrypts to an empty string.
     */
    public static function encrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $raw = base64_encode(Crypt::encryptString($value));

        return self::MARKER.strtr(rtrim($raw, '='), '+/', '-_');
    }

    /**
     * Decrypt a token back to the original identifier.
     *
     * Returns null when the value is not a token or cannot be decrypted with
     * the current application key. Callers must treat null as "not a valid
     * token" rather than "use the value as-is"; use looksEncrypted() first to
     * tell a legacy plaintext value apart from a forged one.
     */
    public static function decrypt(string $value): ?string
    {
        if (! self::looksEncrypted($value)) {
            return null;
        }

        $payload = substr($value, strlen(self::MARKER));

        // Reject anything outside the base64url alphabet outright. PHP's strict
        // base64_decode silently skips whitespace, which would let a padded or
        // space-suffixed variant of a token through; a token this class minted
        // never contains a character outside the set below.
        if ($payload === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $payload)) {
            return null;
        }

        $padded = strtr($payload, '-_', '+/');

        // Restore the base64 padding Crypt::encryptString() stripped.
        $remainder = strlen($padded) % 4;

        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return null;
        }

        try {
            $plain = Crypt::decryptString($decoded);
        } catch (DecryptException) {
            return null;
        }

        // A key rotation (APP_KEY previous_keys) or a hostile token could
        // still yield a non-string or a padded binary; only ever hand back a
        // value that is safe to interpolate into a database lookup.
        return is_string($plain) && $plain !== '' ? $plain : null;
    }

    /**
     * True when the value carries the ciphertext marker.
     *
     * This is the branch that keeps the rollout non-breaking: marked values
     * must decrypt, unmarked values are legacy plaintext.
     */
    public static function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, self::MARKER);
    }

    /**
     * True when the cipher is switched on for this installation.
     */
    public static function enabled(): bool
    {
        return (bool) config('portal.url_cipher.enabled', true);
    }

    /**
     * Encipher a value that will be emitted next to an `id` in a JSON payload,
     * so the client can build an encrypted URL from a record it already holds.
     *
     * Honours the kill switch: with the cipher disabled this returns the raw
     * identifier, so every emitted `ref` stays consistent with resolve() being a
     * passthrough. Minting tokens while disabled would hand the client a value
     * that resolve() then passes through untouched, and the lookup would fail.
     *
     * Non-scalar and empty inputs yield an empty string rather than a token
     * that would decrypt to an empty identifier.
     */
    public static function ref(mixed $id): string
    {
        if (! is_scalar($id) || (string) $id === '') {
            return '';
        }

        return self::enabled() ? self::encrypt((string) $id) : (string) $id;
    }

    /**
     * Resolve a raw URL parameter to the plaintext identifier behind it.
     *
     * Marked values are decrypted or rejected; unmarked values pass through.
     * With the cipher disabled this is a pure passthrough so the kill-switch
     * restores plaintext URLs without touching call sites.
     *
     * @return string|null Plaintext identifier, or null when a marked value
     *                     failed to decrypt and the request must be refused.
     */
    public static function resolve(string $value): ?string
    {
        if (! self::enabled() || ! self::looksEncrypted($value)) {
            return $value;
        }

        return self::decrypt($value);
    }
}
