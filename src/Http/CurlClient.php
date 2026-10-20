<?php

declare(strict_types=1);

namespace IranMessenger\Http;

use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\NetworkException;
use JsonException;

/**
 * cURL based transport used by default in production.
 *
 * All Bot API calls in this library use POST with a JSON body, which is
 * accepted by Telegram, Bale, Eitaa and Rubika. The GET method is provided for
 * custom integrations and health checks.
 */
final class CurlClient implements HttpClient
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
        if ($this->timeout < 1) {
            throw new \InvalidArgumentException('HTTP timeout must be at least 1 second.');
        }

        if ($this->connectTimeout < 1) {
            throw new \InvalidArgumentException('HTTP connect timeout must be at least 1 second.');
        }
    }

    public function post(string $url, array $payload): Response
    {
        try {
            $body = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException(
                'Unable to encode the request payload as JSON: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        return $this->send($url, $body);
    }

    public function get(string $url): Response
    {
        return $this->send($url, null);
    }

    private function send(string $url, ?string $body): Response
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new NetworkException('Only http:// and https:// URLs are allowed.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new NetworkException('Unable to initialize cURL.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'iran-messenger-bot/1.0',
            // Explicit TLS policy: verify the certificate chain and the host
            // name. cURL already defaults to this, but a host application can
            // change the global default, so it is pinned here.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ];

        if ($body !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($raw === false) {
            // cURL error strings can contain the request URL, and the bot token
            // is part of that URL. Scrub it before the exception can reach a log.
            throw new NetworkException(ApiException::redact(
                'cURL request failed: ' . ($error !== '' ? $error : 'unknown error'),
            ));
        }

        return new Response($status, (string) $raw);
    }
}
