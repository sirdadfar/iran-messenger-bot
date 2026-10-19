<?php

declare(strict_types=1);

namespace IranMessenger\Http;

/**
 * Minimal transport abstraction. Adapters only need POST and GET, which keeps
 * the library independent from Guzzle, PSR-18 and framework HTTP clients.
 */
interface HttpClient
{
    /**
     * Send a JSON request body and return the raw provider response.
     *
     * @param array<string, mixed> $payload
     */
    public function post(string $url, array $payload): Response;

    public function get(string $url): Response;
}
