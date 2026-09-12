<?php

return [
    'seed_admin' => [
        'user_id' => env('ADMIN_SEED_USER_ID', 'ADMIN-000001'),
        'email' => env('ADMIN_SEED_EMAIL', 'admin@gmail.com'),
        'password' => env('ADMIN_SEED_PASSWORD', 'password'),
    ],

    /*
    | The academic strands and tracks the registrar recognises. Values that are
    | not on these lists are rejected by the enrollment forms and reverted to
    | the stored value by the portal sync, so a typo or a stale client can never
    | mint a bogus strand/track in the records.
    */
    'strands' => env('PORTAL_STRANDS')
        ? explode(',', env('PORTAL_STRANDS'))
        : ['STEM', 'ABM', 'HUMSS', 'GAS'],

    'tracks' => env('PORTAL_TRACKS')
        ? explode(',', env('PORTAL_TRACKS'))
        : ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'Sports', 'Arts and Design'],

    /*
    | TVET qualifications and training levels the registrar recognises. These
    | stay empty by default and can be managed from the admin Programs page.
    */
    'tvet_qualifications' => env('PORTAL_TVET_QUALIFICATIONS')
        ? explode(',', env('PORTAL_TVET_QUALIFICATIONS'))
        : [],

    'tvet_levels' => env('PORTAL_TVET_LEVELS')
        ? explode(',', env('PORTAL_TVET_LEVELS'))
        : [],

    /*
    | Assigned-section names are free-form (e.g. "Grade 11 - STEM") but bounded
    | to avoid control characters and absurd lengths sneaking into the registry.
    */
    'section_pattern' => env('PORTAL_SECTION_PATTERN', '/^[A-Za-z0-9][A-Za-z0-9 .,\'\/\-]{0,49}$/'),
];
