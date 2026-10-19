<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;

/**
 * Immutable callback query triggered by an inline keyboard.
 */
final class CallbackQuery
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly ?Message $message = null,
        public readonly ?string $data = null,
        public readonly ?string $chatInstance = null,
        public readonly ?string $inlineMessageId = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id']) || !is_string($data['id']) || $data['id'] === '') {
            throw new ValidationException('CallbackQuery payload is missing "id".');
        }

        if (!isset($data['from']) || !is_array($data['from'])) {
            throw new ValidationException('CallbackQuery payload is missing "from".');
        }

        $message = isset($data['message']) && is_array($data['message'])
            ? Message::fromArray($data['message'])
            : null;

        return new self(
            id: $data['id'],
            from: User::fromArray($data['from']),
            message: $message,
            data: isset($data['data']) ? (string) $data['data'] : null,
            chatInstance: isset($data['chat_instance']) ? (string) $data['chat_instance'] : null,
            inlineMessageId: isset($data['inline_message_id']) ? (string) $data['inline_message_id'] : null,
            raw: $data,
        );
    }
}
