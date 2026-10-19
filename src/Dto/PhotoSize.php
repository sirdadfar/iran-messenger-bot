<?php

declare(strict_types=1);

namespace IranMessenger\Dto;

use IranMessenger\Exception\ValidationException;

/**
 * One size variant of a photo.
 */
final class PhotoSize
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $fileId,
        public readonly string $fileUniqueId,
        public readonly int $width,
        public readonly int $height,
        public readonly ?int $fileSize = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['file_id']) || !is_string($data['file_id']) || $data['file_id'] === '') {
            throw new ValidationException('PhotoSize payload is missing "file_id".');
        }

        return new self(
            fileId: $data['file_id'],
            fileUniqueId: (string) ($data['file_unique_id'] ?? ''),
            width: (int) ($data['width'] ?? 0),
            height: (int) ($data['height'] ?? 0),
            fileSize: isset($data['file_size']) ? (int) $data['file_size'] : null,
            raw: $data,
        );
    }
}
