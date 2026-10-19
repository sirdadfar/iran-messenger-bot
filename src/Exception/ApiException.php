<?php

declare(strict_types=1);

namespace IranMessenger\Exception;

use RuntimeException;
use Stringable;
use Throwable;

/**
 * Provider error: non-2xx HTTP status, `{"ok": false}` or a Rubika
 * `{"status": "..."}` envelope that is not OK.
 *
 * Bot tokens are scrubbed from the message, from `__toString()` and from the
 * stored payload/response so the exception is safe to log.
 */
final class ApiException extends RuntimeException implements Stringable
{
    private readonly string $method;

    /** @var array<string, mixed> */
    private readonly array $payload;

    /** @var array<string, mixed>|string|null */
    private readonly array|string|null $response;

    private readonly ?int $httpStatus;

    private readonly int|string|null $providerCode;

    /** @var list<string> */
    private readonly array $secrets;

    /**
     * @param array<string, mixed>          $payload
     * @param array<string, mixed>|string|null $response
     * @param list<string>                  $secrets Strings that must never appear in logs
     */
    public function __construct(
        string $message,
        string $method,
        array $payload = [],
        array|string|null $response = null,
        ?int $httpStatus = null,
        int|string|null $providerCode = null,
        array $secrets = [],
        ?Throwable $previous = null,
    ) {
        $this->method = $method;
        $this->secrets = array_values(array_filter(
            $secrets,
            static fn (mixed $secret): bool => is_string($secret) && $secret !== '',
        ));
        $this->payload = self::redactValue($payload, $this->secrets);
        $this->response = self::redactValue($response, $this->secrets);
        $this->httpStatus = $httpStatus;
        $this->providerCode = $providerCode;

        parent::__construct(
            self::redact($message, $this->secrets),
            is_int($providerCode) ? $providerCode : 0,
            $previous,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function getResponse(): array|string|null
    {
        return $this->response;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getProviderCode(): int|string|null
    {
        return $this->providerCode;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: [%s] %s in %s:%d',
            self::class,
            $this->method,
            $this->getMessage(),
            $this->getFile(),
            $this->getLine(),
        );
    }

    /**
     * Replace known secrets and Telegram-style token patterns in a string.
     *
     * @param list<string> $secrets
     */
    public static function redact(string $text, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }

            $text = str_replace([$secret, rawurlencode($secret)], '[redacted]', $text);
        }

        // Catch tokens that were not passed explicitly, e.g. from a provider
        // response: 123456789:AAH... .
        return preg_replace('/bot\d{5,}:[A-Za-z0-9_-]{10,}/', 'bot[redacted]', $text) ?? $text;
    }

    /**
     * @param list<string> $secrets
     */
    private static function redactValue(mixed $value, array $secrets): mixed
    {
        if (is_string($value)) {
            return self::redact($value, $secrets);
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                $clean[$key] = self::redactValue($item, $secrets);
            }

            return $clean;
        }

        return $value;
    }
}
