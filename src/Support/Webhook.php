<?php

declare(strict_types=1);

namespace IranMessenger\Support;

use IranMessenger\Dto\Update;
use IranMessenger\Exception\ValidationException;

/**
 * Helpers for receiving updates over HTTP.
 *
 * A bot webhook is a public, unauthenticated endpoint: anyone who learns the
 * URL can post fake updates. Telegram (and Bale, which follows the same Bot
 * API shape) can send a shared secret in a header when the webhook is
 * registered with `secret_token`. Verify that header with `verify()` before
 * trusting a single field of the body.
 *
 * Platforms that do not support a secret header should put their own random
 * token in the URL path or verify a signature in their framework; this class
 * cannot invent a signature scheme the provider does not send. That limitation
 * is stated explicitly in README.fa.md and SECURITY.md.
 */
final class Webhook
{
    /**
     * Header Telegram uses for `setWebhook(secret_token: ...)`, in $_SERVER form.
     */
    public const SERVER_HEADER = 'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN';

    /**
     * Same header as sent over the wire.
     */
    public const HEADER_NAME = 'X-Telegram-Bot-Api-Secret-Token';

    /**
     * Upper bound for a webhook body. Provider updates are small; anything
     * larger is a sign of abuse and is rejected before json_decode().
     */
    public const MAX_BODY_BYTES = 1_048_576;

    /**
     * longest secret Telegram accepts.
     */
    public const MAX_SECRET_LENGTH = 256;

    /**
     * A secret must be 1–256 characters of A–Z, a–z, 0–9, underscore or dash,
     * which is exactly what Telegram accepts for `secret_token`.
     */
    public static function isValidSecret(string $secret): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret) === 1;
    }

    /**
     * Constant-time comparison of a provided secret with the expected value.
     *
     * `hash_equals()` is used instead of `===` so the comparison does not leak
     * the position of the first differing byte through timing.
     */
    public static function verify(?string $provided, string $expected): bool
    {
        if ($provided === null || $provided === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Verify the secret header from a $_SERVER-like array.
     *
     * The header name is configurable for platforms with their own spelling;
     * lookup is case-insensitive and accepts both `HTTP_X_Y` and `x-y` keys.
     *
     * @param array<string, mixed> $server
     */
    public static function verifyServerHeader(
        array $server,
        string $expected,
        string $header = self::SERVER_HEADER,
    ): bool {
        return self::verify(self::header($server, $header), $expected);
    }

    /**
     * Read one header value from a $_SERVER-like array.
     *
     * @param array<string, mixed> $server
     */
    public static function header(array $server, string $name = self::SERVER_HEADER): ?string
    {
        $wanted = self::normaliseHeaderName($name);

        foreach ($server as $key => $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            if (self::normaliseHeaderName((string) $key) === $wanted) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalise `HTTP_X_Y`, `X-Y` and `HTTP-X-Y` to the same comparable form.
     */
    private static function normaliseHeaderName(string $name): string
    {
        $name = strtolower(str_replace('_', '-', $name));

        if (str_starts_with($name, 'http-')) {
            $name = substr($name, 5);
        }

        return $name;
    }

    /**
     * Decode a raw webhook body into an array, with a byte cap and clear errors.
     *
     * @return array<string, mixed>
     */
    public static function decode(string $rawBody, int $maxBytes = self::MAX_BODY_BYTES): array
    {
        if ($maxBytes < 1) {
            throw new ValidationException('The webhook body limit must be a positive number of bytes.');
        }

        if ($rawBody === '') {
            throw new ValidationException('Webhook body is empty.');
        }

        if (strlen($rawBody) > $maxBytes) {
            throw new ValidationException(sprintf('Webhook body exceeds the %d byte limit.', $maxBytes));
        }

        try {
            $decoded = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ValidationException('Webhook body is not valid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new ValidationException('Webhook body must be a JSON object.');
        }

        return $decoded;
    }

    /**
     * Decode and parse a body in one step.
     *
     * @return Update
     */
    public static function update(string $rawBody, int $maxBytes = self::MAX_BODY_BYTES): Update
    {
        return Update::fromArray(self::decode($rawBody, $maxBytes));
    }

    /**
     * Generate a URL-safe secret suitable for `setWebhook(secret_token: ...)`.
     */
    public static function generateSecret(int $bytes = 32): string
    {
        if ($bytes < 16) {
            throw new ValidationException('A webhook secret must use at least 16 random bytes.');
        }

        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
