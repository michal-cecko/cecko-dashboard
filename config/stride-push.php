<?php

return [
    'public_key' => env('STRIDE_PUSH_PUBLIC_KEY'),
    'private_key' => env('STRIDE_PUSH_PRIVATE_KEY'),
    'subject' => env('STRIDE_PUSH_SUBJECT'),
    'web_url' => env('STRIDE_WEB_URL', 'https://stride.cecko.dev'),
];
