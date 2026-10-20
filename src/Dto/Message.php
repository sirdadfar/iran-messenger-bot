<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;
use IranMessenger\Support\Value;

/**
 * Immutable incoming message.
 */
final class Message
{
    /**
     * @param list<PhotoSize>          $photo
     * @param array<string, mixed>|null $document
     * @param array<string, mixed>      $raw
     */
    public function __construct(
        public readonly int $messageId,
        public readonly int $date,
        public readonly Chat $chat,
        public readonly ?User $from = null,
        public readonly ?string $text = null,
        public readonly ?string $caption = null,
        public readonly array $photo = [],
        public readonly ?array $document = null,
        public readonly ?Message $replyToMessage = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['message_id']) || !is_numeric($data['message_id'])) {
            throw new ValidationException('Message payload is missing a numeric "message_id".');
        }

        if (!isset($data['chat']) || !is_array($data['chat'])) {
            throw new ValidationException('Message payload is missing "chat".');
        }

        $photo = [];

        foreach (is_array($data['photo'] ?? null) ? $data['photo'] : [] as $size) {
            if (is_array($size)) {
                $photo[] = PhotoSize::fromArray($size);
            }
        }

        $from = isset($data['from']) && is_array($data['from'])
            ? User::fromArray($data['from'])
            : null;

        $replyTo = isset($data['reply_to_message']) && is_array($data['reply_to_message'])
            ? self::fromArray($data['reply_to_message'])
            : null;

        return new self(
            messageId: (int) $data['message_id'],
            date: (int) ($data['date'] ?? 0),
            chat: Chat::fromArray($data['chat']),
            from: $from,
            text: Value::string($data['text'] ?? null),
            caption: Value::string($data['caption'] ?? null),
            photo: $photo,
            document: isset($data['document']) && is_array($data['document']) ? $data['document'] : null,
            replyToMessage: $replyTo,
            raw: $data,
        );
    }

    public function isCommand(): bool
    {
        return $this->text !== null && str_starts_with($this->text, '/');
    }

    public function hasPhoto(): bool
    {
        return $this->photo !== [];
    }
}
