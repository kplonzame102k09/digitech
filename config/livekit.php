<?php

return [
    /*
    | Self-hosted LiveKit SFU for per-classroom video (Meet-style).
    | The app never handles media; it only mints short-lived join tokens.
    | Secrets stay server-side — never expose them to public/js.
    |
    | Provision: livekit-server + coturn (see docker-compose.livekit.yml
    | example in docs). Browsers require HTTPS/wss for mic/cam.
    */
    'url' => env('LIVEKIT_URL', ''),
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),

    // Join-token lifetime in seconds.
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 7200),

    // Persistent instant room prefix per classroom: class-{id-slug}.
    'room_prefix' => env('LIVEKIT_ROOM_PREFIX', 'class-'),

    // Disabled until URL+key+secret are set; UI shows setup notice instead.
    'enabled' => (bool) (env('LIVEKIT_URL') && env('LIVEKIT_API_KEY') && env('LIVEKIT_API_SECRET')),
];
