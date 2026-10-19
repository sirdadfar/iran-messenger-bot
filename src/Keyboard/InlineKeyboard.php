<?php

declare(strict_types=1);

namespace IranMessenger\Keyboard;

use IranMessenger\Exception\ValidationException;

/**
 * Fluent, immutable inline keyboard.
 *
 * ```php
 * $keyboard = InlineKeyboard::make()
 *     ->row([
 *         InlineKeyboard::callback('Yes', 'yes'),
 *         InlineKeyboard::url('Docs', 'https://example.com'),
 *     ]);
 * ```
 */
final class InlineKeyboard
{
    /** @var list<list<array{text: string, callback_data?: string, url?: string}>> */
    private array $rows = [];

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * @return array{text: string, callback_data: string}
     */
    public static function callback(string $text, string $data): array
    {
        if (trim($text) === '') {
            throw new ValidationException('Inline button text must not be empty.');
        }

        if ($data === '') {
            throw new ValidationException('Inline button callback data must not be empty.');
        }

        if (strlen($data) > 64) {
            throw new ValidationException('Inline button callback data must not exceed 64 bytes.');
        }

        return ['text' => $text, 'callback_data' => $data];
    }

    /**
     * @return array{text: string, url: string}
     */
    public static function url(string $text, string $url): array
    {
        if (trim($text) === '') {
            throw new ValidationException('Inline button text must not be empty.');
        }

        $url = trim($url);

        if (preg_match('~^(https?|tg)://~i', $url) !== 1 || preg_match('/\s/', $url) === 1) {
            throw new ValidationException('Inline button URL must start with http://, https:// or tg:// and contain no whitespace.');
        }

        return ['text' => $text, 'url' => $url];
    }

    /**
     * Append a row. Every button must be an array with `text` and exactly one
     * of `callback_data` or `url`.
     *
     * @param list<array<string, mixed>> $buttons
     */
    public function row(array $buttons): self
    {
        if ($buttons === []) {
            throw new ValidationException('An inline keyboard row must contain at least one button.');
        }

        $normalized = [];

        foreach ($buttons as $button) {
            if (!is_array($button)) {
                throw new ValidationException('Inline keyboard buttons must be arrays.');
            }

            $normalized[] = self::normalizeButton($button);
        }

        $clone = clone $this;
        $clone->rows[] = $normalized;

        return $clone;
    }

    /**
     * @return array{inline_keyboard: list<list<array<string, string>>>}
     */
    public function toArray(): array
    {
        return ['inline_keyboard' => $this->rows];
    }

    /**
     * @param array<string, mixed> $button
     * @return array{text: string, callback_data?: string, url?: string}
     */
    private static function normalizeButton(array $button): array
    {
        $text = $button['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new ValidationException('Inline button text must not be empty.');
        }

        $hasCallback = array_key_exists('callback_data', $button);
        $hasUrl = array_key_exists('url', $button);

        if ($hasCallback === $hasUrl) {
            throw new ValidationException(
                'An inline button must contain exactly one of "callback_data" or "url".',
            );
        }

        if ($hasCallback) {
            $data = $button['callback_data'];

            if (!is_string($data) || $data === '') {
                throw new ValidationException('Inline button callback data must not be empty.');
            }

            if (strlen($data) > 64) {
                throw new ValidationException('Inline button callback data must not exceed 64 bytes.');
            }

            return ['text' => $text, 'callback_data' => $data];
        }

        $url = $button['url'];

        if (
            !is_string($url)
            || preg_match('~^(https?|tg)://~i', trim($url)) !== 1
            || preg_match('/\s/', trim($url)) === 1
        ) {
            throw new ValidationException('Inline button URL must start with http://, https:// or tg:// and contain no whitespace.');
        }

        return ['text' => $text, 'url' => trim($url)];
    }
}
