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
];
