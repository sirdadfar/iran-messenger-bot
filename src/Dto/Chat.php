<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;

/**
 * Immutable chat (private, group, supergroup or channel).
 */
final class Chat
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $title = null,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id']) || !is_numeric($data['id'])) {
            throw new ValidationException('Chat payload is missing a numeric "id".');
        }

        return new self(
            id: (int) $data['id'],
            type: (string) ($data['type'] ?? 'unknown'),
            title: isset($data['title']) ? (string) $data['title'] : null,
            username: isset($data['username']) ? (string) $data['username'] : null,
            firstName: isset($data['first_name']) ? (string) $data['first_name'] : null,
            lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
            raw: $data,
        );
    }

    public function displayName(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title;
        }

        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $name !== '' ? $name : ($this->username ?? (string) $this->id);
    }
}
