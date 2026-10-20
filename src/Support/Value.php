<?php

declare(strict_types=1);

namespace IranMessenger\Support;

/**
 * Defensive scalar access for provider payloads.
 *
 * Webhook bodies come from the network and are fully attacker-controlled up to
 * the point where a signature/secret is verified. Casting `(string) []` or
 * `(string) new stdClass()` raises a warning (or an ErrorException in Laravel),
 * so DTO factories go through these helpers instead of raw casts.
 */
final class Value
{
    public static function string(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    public static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
