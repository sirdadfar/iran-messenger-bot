<?php

declare(strict_types=1);

return [
    /*
     * Driver: telegram, bale, eitaa or rubika.
     */
    'driver' => env('IRAN_MESSENGER_DRIVER', 'telegram'),

    /*
     * Bot token. Never commit this value or expose it to the browser.
     */
    'token' => env('IRAN_MESSENGER_TOKEN', ''),

    /*
     * Adapter options: base_url, timeout, connect_timeout,
     * max_message_length, methods.
     */
    'options' => [
        // 'base_url' => 'https://api.telegram.org',
        // 'timeout' => 30,
    ],
];
