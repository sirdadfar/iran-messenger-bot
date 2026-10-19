<?php

declare(strict_types=1);

namespace IranMessenger\Adapter;

use IranMessenger\Exception\ValidationException;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Keyboard\ReplyKeyboard;
use IranMessenger\Messenger;

/**
 * Rubika adapter built against the public Bot API v3 shape:
 * https://botapi.rubika.ir/v3/{token}/{method}
 *
 * Rubika differs from Telegram:
 *  - files (photos and documents) are sent through `sendFile`;
 *  - reply keypads use `chat_keypad` / `chat_keypad_type` instead of
 *    `reply_markup`;
 *  - there is no inline keyboard in the public shape;
 *  - chat discovery uses `getChats`.
 *
 * The exact keypad object and method set should be verified against the API
 * version enabled for your bot account.
 */
final class RubikaAdapter extends AbstractAdapter
{
    private const FEATURES = [
        Messenger::FEATURE_SEND_MESSAGE,
        Messenger::FEATURE_PHOTO,
        Messenger::FEATURE_DOCUMENT,
        Messenger::FEATURE_REPLY_KEYBOARD,
        Messenger::FEATURE_GET_ME,
        Messenger::FEATURE_POLLING,
    ];

    public function driver(): string
    {
        return 'rubika';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://botapi.rubika.ir/v3';
    }

    protected function features(): array
    {
        return self::FEATURES;
    }

    protected function defaultMaxMessageLength(): int
    {
        return 4096;
    }

    protected function endpoint(string $method): string
    {
        return sprintf('%s/%s/%s', $this->baseUrl(), $this->token(), $method);
    }

    public function sendPhoto(int|string $chatId, string $photo, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_PHOTO);

        return $this->sendFile($chatId, $photo, $options);
    }

    public function sendDocument(int|string $chatId, string $document, array $options = []): array
    {
        return $this->sendFile($chatId, $document, $options);
    }

    /**
     * Native Rubika file endpoint. `$file` is a file id or a public URL,
     * depending on what your bot account accepts.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendFile(int|string $chatId, string $file, array $options = []): array
    {
        $payload = $options;
        $payload['chat_id'] = $this->normalizeChatId($chatId);
        $payload['file'] = $this->requireNonEmpty($file, 'file');

        return $this->call('sendFile', $payload);
    }

    /**
     * List chats the bot can see. Rubika-specific, not part of the Messenger
     * interface.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function getChats(array $options = []): array
    {
        return $this->call('getChats', $options);
    }

    /**
     * Rubika wraps successful responses in `{"status": "OK", "data": ...}`.
     *
     * @param array<string, mixed> $data
     */
    protected function failureDescription(array $data, int $status): ?string
    {
        if ($status < 200 || $status >= 300) {
            return (string) ($data['message'] ?? $data['description'] ?? sprintf('HTTP %d', $status));
        }

        if (($data['status'] ?? null) === 'OK') {
            return null;
        }

        return (string) ($data['message'] ?? $data['status_det'] ?? $data['status'] ?? 'Unknown Rubika API error');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function applyKeyboard(array $payload, mixed $keyboard): array
    {
        if ($keyboard instanceof InlineKeyboard) {
            $this->assertFeature(self::FEATURE_INLINE_KEYBOARD);
        }

        if (is_array($keyboard) && array_key_exists('inline_keyboard', $keyboard)) {
            $this->assertFeature(self::FEATURE_INLINE_KEYBOARD);
        }

        if ($keyboard instanceof ReplyKeyboard) {
            $this->assertFeature(self::FEATURE_REPLY_KEYBOARD);
            $rows = $keyboard->toArray()['keyboard'];

            $payload['chat_keypad'] = $this->chatKeypad($rows);
            $payload['chat_keypad_type'] = 'New';

            return $payload;
        }

        if (is_array($keyboard) && array_key_exists('keyboard', $keyboard)) {
            $this->assertFeature(self::FEATURE_REPLY_KEYBOARD);

            if (!is_array($keyboard['keyboard'])) {
                throw new ValidationException('The "keyboard" value must be an array of rows.');
            }

            $payload['chat_keypad'] = $this->chatKeypad($keyboard['keyboard']);
            $payload['chat_keypad_type'] = 'New';

            return $payload;
        }

        throw new ValidationException('Rubika supports ReplyKeyboard (chat_keypad) only; inline keyboards are unavailable.');
    }

    /**
     * @param array<int, mixed> $rows
     * @return array{rows: list<array{buttons: list<array{button_text: string}>}>}
     */
    private function chatKeypad(array $rows): array
    {
        $keypadRows = [];

        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                throw new ValidationException('A keypad row must contain at least one button.');
            }

            $buttons = [];

            foreach ($row as $button) {
                $text = is_array($button) ? ($button['text'] ?? null) : $button;

                if (!is_string($text) || trim($text) === '') {
                    throw new ValidationException('Every keypad button needs a non-empty text.');
                }

                $buttons[] = ['button_text' => $text];
            }

            $keypadRows[] = ['buttons' => $buttons];
        }

        return ['rows' => $keypadRows];
    }
}
