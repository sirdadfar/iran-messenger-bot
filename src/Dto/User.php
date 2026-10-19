<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;

/**
 * Immutable messenger user.
 */
final class User
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $id,
        public readonly bool $isBot,
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly ?string $username = null,
        public readonly ?string $languageCode = null,
        public readonly ?bool $isPremium = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id']) || !is_numeric($data['id'])) {
            throw new ValidationException('User payload is missing a numeric "id".');
        }

        return new self(
            id: (int) $data['id'],
            isBot: (bool) ($data['is_bot'] ?? false),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
            username: isset($data['username']) ? (string) $data['username'] : null,
            languageCode: isset($data['language_code']) ? (string) $data['language_code'] : null,
            isPremium: isset($data['is_premium']) ? (bool) $data['is_premium'] : null,
            raw: $data,
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . ($this->lastName ?? ''));
    }
}
