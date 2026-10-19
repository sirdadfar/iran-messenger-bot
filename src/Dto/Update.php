<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;

/**
 * Immutable webhook/polling update.
 *
 * Only the update kinds relevant to bot messaging are parsed. The complete
 * provider payload is always available through `raw`.
 */
final class Update
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $updateId,
        public readonly ?Message $message = null,
        public readonly ?Message $editedMessage = null,
        public readonly ?CallbackQuery $callbackQuery = null,
        public readonly ?Message $channelPost = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['update_id']) || !is_numeric($data['update_id'])) {
            throw new ValidationException('Update payload is missing a numeric "update_id".');
        }

        return new self(
            updateId: (int) $data['update_id'],
            message: self::message($data, 'message'),
            editedMessage: self::message($data, 'edited_message'),
            callbackQuery: isset($data['callback_query']) && is_array($data['callback_query'])
                ? CallbackQuery::fromArray($data['callback_query'])
                : null,
            channelPost: self::message($data, 'channel_post'),
            raw: $data,
        );
    }

    public function type(): string
    {
        return match (true) {
            $this->message !== null => 'message',
            $this->editedMessage !== null => 'edited_message',
            $this->callbackQuery !== null => 'callback_query',
            $this->channelPost !== null => 'channel_post',
            default => 'unknown',
        };
    }

    public function isCallbackQuery(): bool
    {
        return $this->callbackQuery !== null;
    }

    public function isEditedMessage(): bool
    {
        return $this->editedMessage !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function message(array $data, string $key): ?Message
    {
        return isset($data[$key]) && is_array($data[$key])
            ? Message::fromArray($data[$key])
            : null;
    }
}
