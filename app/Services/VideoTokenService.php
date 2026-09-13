<?php

namespace App\Services;

use App\Models\User;

/**
 * Mints LiveKit join tokens (hand-rolled HS256 JWT, no extra deps).
 *
 * Token layout follows LiveKit access-token conventions:
 *   iss = API key, sub = identity (user_id), video grant with room +
 *   publish/subscribe rights. Teachers get room-admin (moderator) rights.
 */
class VideoTokenService
{
    public function isEnabled(): bool
    {
        return (bool) config('livekit.enabled');
    }

    public function serverUrl(): string
    {
        return (string) config('livekit.url');
    }

    public function roomForClassroom(string $classroomId, ?string $meetingId = null): string
    {
        $prefix = (string) config('livekit.room_prefix', 'class-');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $classroomId));
        $slug = trim($slug, '-') ?: 'room';

        if ($meetingId) {
            $m = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $meetingId));
            $m = trim($m, '-') ?: 'session';

            return substr($prefix.$slug.'-'.$m, 0, 120);
        }

        return substr($prefix.$slug, 0, 120);
    }

    /**
     * @return array{url:string,token:string,room:string,expiresIn:int}
     */
    public function mint(User $user, string $classroomId, ?string $meetingId = null, ?int $ttl = null): array
    {
        abort_unless($this->isEnabled(), 503, 'Video conferencing is not configured yet.');

        $key = (string) config('livekit.api_key');
        $secret = (string) config('livekit.api_secret');
        abort_unless($key !== '' && $secret !== '', 503, 'Video conferencing is not configured yet.');

        $now = time();
        $exp = $now + ($ttl ?? (int) config('livekit.token_ttl', 7200));
        $room = $this->roomForClassroom($classroomId, $meetingId);
        $moderator = $user->isTeacher() || $user->isAdmin();

        $payload = [
            'iss' => $key,
            'sub' => $user->user_id,
            'name' => trim($user->firstName.' '.$user->lastName) ?: $user->user_id,
            'nbf' => $now - 5,
            'exp' => $exp,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
                'roomAdmin' => $moderator,
                'hidden' => false,
            ],
        ];

        return [
            'url' => $this->serverUrl(),
            'token' => self::signHs256($payload, $secret),
            'room' => $room,
            'expiresIn' => $exp - $now,
            'moderator' => $moderator,
        ];
    }

    public static function signHs256(array $payload, string $secret): string
    {
        $b64 = fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $segments = [
            $b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $b64((string) json_encode($payload)),
        ];
        $signing = implode('.', $segments);
        $segments[] = $b64(hash_hmac('sha256', $signing, $secret, true));

        return implode('.', $segments);
    }
}
