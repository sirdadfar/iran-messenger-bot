<?php

declare(strict_types=1);

namespace IranMessenger\Adapter;

use IranMessenger\Messenger;

/**
 * Bale adapter.
 *
 * Bale exposes a Telegram-compatible bot API at
 * https://tapi.bale.ai/bot{token}/{method}, so the Telegram method subset is
 * supported. The adapter is built against that public shape; verify the base
 * URL/version for your account.
 */
final class BaleAdapter extends AbstractAdapter
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
        return 'bale';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://tapi.bale.ai';
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
