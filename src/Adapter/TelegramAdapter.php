<?php

declare(strict_types=1);

namespace IranMessenger\Adapter;

use IranMessenger\Messenger;

/**
 * Telegram Bot API adapter.
 *
 * Endpoint: https://api.telegram.org/bot{token}/{method}
 *
 * `base_url` can point at a self-hosted Bot API server. `methods` can override
 * any logical method name if your account/API version differs.
 */
final class TelegramAdapter extends AbstractAdapter
{
    private const FEATURES = [
        Messenger::FEATURE_SEND_MESSAGE,
        Messenger::FEATURE_PHOTO,
        Messenger::FEATURE_DOCUMENT,
        Messenger::FEATURE_INLINE_KEYBOARD,
        Messenger::FEATURE_REPLY_KEYBOARD,
        Messenger::FEATURE_EDIT_MESSAGE,
        Messenger::FEATURE_DELETE_MESSAGE,
        Messenger::FEATURE_CALLBACK_QUERY,
        Messenger::FEATURE_WEBHOOK,
        Messenger::FEATURE_POLLING,
        Messenger::FEATURE_GET_ME,
    ];

    private const METHODS = [
        'editMessage' => 'editMessageText',
    ];

    public function driver(): string
    {
        return 'telegram';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.telegram.org';
    }

    protected function features(): array
    {
        return self::FEATURES;
    }

    protected function defaultMaxMessageLength(): int
    {
        return 4096;
    }

    protected function methodMap(): array
    {
        return self::METHODS;
    }
}
