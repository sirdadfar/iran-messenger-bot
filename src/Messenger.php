<?php

declare(strict_types=1);

namespace IranMessenger;

/**
 * The single contract every supported messenger adapter implements.
 *
 * Methods return the decoded provider envelope (for example
 * `['ok' => true, 'result' => [...]]` for Telegram-compatible APIs) so callers
 * can access provider-specific fields without losing information.
 */
interface Messenger
{
    public const FEATURE_SEND_MESSAGE = 'send_message';
    public const FEATURE_PHOTO = 'photo';
    public const FEATURE_DOCUMENT = 'document';
    public const FEATURE_INLINE_KEYBOARD = 'inline_keyboard';
    public const FEATURE_REPLY_KEYBOARD = 'reply_keyboard';
    public const FEATURE_EDIT_MESSAGE = 'edit_message';
    public const FEATURE_DELETE_MESSAGE = 'delete_message';
    public const FEATURE_CALLBACK_QUERY = 'callback_query';
    public const FEATURE_WEBHOOK = 'webhook';
    public const FEATURE_POLLING = 'polling';
    public const FEATURE_GET_ME = 'get_me';

    /**
     * @param array<string, mixed> $options Provider parameters plus `keyboard`
     * @return array<string, mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendPhoto(int|string $chatId, string $photo, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendDocument(int|string $chatId, string $document, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function editMessage(int|string $chatId, int|string $messageId, string $text, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $callbackQueryId, array $options = []): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteMessage(int|string $chatId, int|string $messageId): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function getUpdates(array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function deleteWebhook(array $options = []): array;

    /**
     * @return array<string, mixed>
     */
    public function getMe(): array;

    public function supports(string $feature): bool;
}
