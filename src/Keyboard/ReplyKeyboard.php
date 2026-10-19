<?php

declare(strict_types=1);

namespace IranMessenger\Keyboard;

use IranMessenger\Exception\ValidationException;

/**
 * Fluent, immutable custom reply keyboard.
 *
 * ```php
 * $keyboard = ReplyKeyboard::make()
 *     ->row(['Yes', 'No'])
 *     ->resize()
 *     ->oneTime();
 * ```
 */
final class ReplyKeyboard
{
    /** @var list<list<array<string, mixed>>> */
    private array $rows = [];

    private bool $resize = false;

    private bool $oneTime = false;

    private bool $selective = false;

    private ?string $placeholder = null;

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * Append a row of buttons. A button is either a string or an array with a
     * non-empty `text` key plus optional Telegram fields such as
     * `request_contact` or `request_location`.
     *
     * @param list<string|array<string, mixed>> $buttons
     */
    public function row(array $buttons): self
    {
        if ($buttons === []) {
            throw new ValidationException('A reply keyboard row must contain at least one button.');
        }

        $normalized = [];

        foreach ($buttons as $button) {
            $normalized[] = self::normalizeButton($button);
        }

        $clone = clone $this;
        $clone->rows[] = $normalized;

        return $clone;
    }

    public function resize(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->resize = $enabled;

        return $clone;
    }

    public function oneTime(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->oneTime = $enabled;

        return $clone;
    }

    public function selective(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->selective = $enabled;

        return $clone;
    }

    /**
     * Telegram 7.0+ input field placeholder. Ignored by providers that do not
     * understand the field.
     */
    public function placeholder(string $text): self
    {
        if (mb_strlen($text, 'UTF-8') > 64) {
            throw new ValidationException('Keyboard placeholder must not exceed 64 characters.');
        }

        $clone = clone $this;
        $clone->placeholder = $text;

        return $clone;
    }

    /**
     * @return array{
     *     keyboard: list<list<array<string, mixed>>>,
     *     resize_keyboard?: true,
     *     one_time_keyboard?: true,
     *     selective?: true,
     *     input_field_placeholder?: string
     * }
     */
    public function toArray(): array
    {
        $result = ['keyboard' => $this->rows];

        if ($this->resize) {
            $result['resize_keyboard'] = true;
        }

        if ($this->oneTime) {
            $result['one_time_keyboard'] = true;
        }

        if ($this->selective) {
            $result['selective'] = true;
        }

        if ($this->placeholder !== null) {
            $result['input_field_placeholder'] = $this->placeholder;
        }

        return $result;
    }

    /**
     * @param string|array<string, mixed> $button
     * @return array<string, mixed>
     */
    private static function normalizeButton(string|array $button): array
    {
        if (is_string($button)) {
            if (trim($button) === '') {
                throw new ValidationException('Reply button text must not be empty.');
            }

            return ['text' => $button];
        }

        $text = $button['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new ValidationException('Reply button text must not be empty.');
        }

        return $button;
    }
}
