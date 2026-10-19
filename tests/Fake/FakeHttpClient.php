<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Fake;

use IranMessenger\Http\HttpClient;
use IranMessenger\Http\Response;
use RuntimeException;

/**
 * Deterministic HTTP double. Responses are returned in FIFO order and every
 * request is recorded for assertions.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<array{method: string, url: string, payload: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<Response> */
    private array $queue = [];

    /**
     * @param list<Response> $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $response) {
            $this->queue($response);
        }
    }

    public function queue(Response $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function json(array $body, int $status = 200): Response
    {
        return new Response(
            $status,
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function ok(mixed $result): Response
    {
        return self::json(['ok' => true, 'result' => $result]);
    }

    public function post(string $url, array $payload): Response
    {
        $this->requests[] = ['method' => 'POST', 'url' => $url, 'payload' => $payload];

        return $this->next();
    }

    public function get(string $url): Response
    {
        $this->requests[] = ['method' => 'GET', 'url' => $url, 'payload' => []];

        return $this->next();
    }

    /**
     * @return array{method: string, url: string, payload: array<string, mixed>}
     */
    public function lastRequest(): array
    {
        if ($this->requests === []) {
            throw new RuntimeException('No HTTP request was recorded.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }

    private function next(): Response
    {
        if ($this->queue === []) {
            throw new RuntimeException('FakeHttpClient has no queued response.');
        }

        return array_shift($this->queue);
    }
}
