<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Forum Knowledge Sources
    |--------------------------------------------------------------------------
    |
    | Each source defines where to look for community wisdom about a model.
    | The garaz:fetch-forum-knowledge command iterates these and creates
    | KnowledgeNote rows tagged source=forum.
    |
    | TOS-friendly only: motor-talk.de, public reddit JSON, dedicated forums
    | with public threads. NEVER scrape Facebook groups (TOS violation +
    | account-flagging risk).
    |
    | Each source descriptor:
    |   - name: human-readable label
    |   - kind: 'reddit_json' | 'http_html' | 'rss'
    |   - url: starting URL (search query for reddit, forum thread index for html)
    |   - vehicle_match: ['make' => 'Opel', 'model' => 'Astra K'] — applies to
    |     vehicles with this make/model so notes link automatically
    |   - keywords: array of must-match keywords for an item to be saved
    |   - rate_limit_seconds: minimum seconds between requests to this domain
    |
    */

    'forum_sources' => [
        // [
        //     'name' => 'Reddit /r/Opel',
        //     'kind' => 'reddit_json',
        //     'url' => 'https://www.reddit.com/r/Opel/search.json?q=Astra+K&restrict_sr=1&sort=new',
        //     'vehicle_match' => ['make' => 'Opel', 'model' => 'Astra'],
        //     'keywords' => ['B14XFT', 'timing chain', 'EGR', 'DPF'],
        //     'rate_limit_seconds' => 5,
        // ],
        // [
        //     'name' => 'motor-talk.de — Astra K',
        //     'kind' => 'http_html',
        //     'url' => 'https://www.motor-talk.de/forum/opel-astra-k-b231.html',
        //     'vehicle_match' => ['make' => 'Opel', 'model' => 'Astra'],
        //     'keywords' => [],
        //     'rate_limit_seconds' => 10,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Forum Ingest User
    |--------------------------------------------------------------------------
    |
    | The user_id under which forum-sourced knowledge notes are saved.
    | Set this to your account id; the command will skip if null.
    |
    */
    'forum_ingest_user_id' => env('GARAZ_FORUM_INGEST_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Maintenance intervals
    |--------------------------------------------------------------------------
    |
    | Planning intervals per service category (months and/or km, null = no
    | limit on that axis) used by the dashboard due-maintenance widget: an item
    | is overdue once either axis is exceeded since the last record of that
    | category, and "due soon" within the thresholds below. Categories not
    | listed here are not tracked. STK/EK expiry is covered by vehicle
    | documents, not here.
    |
    */

    'maintenance_intervals' => [
        'oil_change' => ['months' => 12, 'km' => 15_000],
        'cabin_filter' => ['months' => 24, 'km' => 60_000],
        'air_filter' => ['months' => 48, 'km' => 60_000],
        'spark_plugs' => ['months' => 48, 'km' => 60_000],
        'brakes' => ['months' => 24, 'km' => null],
        'coolant' => ['months' => 72, 'km' => null],
    ],

    'due_soon_days' => 60,
    'due_soon_km' => 2_000,
];
