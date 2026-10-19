<?php

declare(strict_types=1);

namespace IranMessenger\Adapter;

use IranMessenger\Messenger;

/**
 * Eitaa adapter built against the public EitaaYar API shape:
 * https://eitaayar.ir/api/{token}/{method}
 *
 * Eitaa is a send-oriented API. Inline keyboards, editing, deletion, callback
 * queries, webhooks and polling are not part of the public shape this adapter
 * targets, so they are reported as unsupported and throw.
 */
final class EitaaAdapter extends AbstractAdapter
{
    private const FEATURES = [
        Messenger::FEATURE_SEND_MESSAGE,
        Messenger::FEATURE_DOCUMENT,
        Messenger::FEATURE_GET_ME,
    ];

    public function driver(): string
    {
        return 'eitaa';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://eitaayar.ir/api';
    }

    protected function features(): array
    {
        return self::FEATURES;
    }

    protected function defaultMaxMessageLength(): int
    {
        return 1000;
    }

    protected function endpoint(string $method): string
    {
        return sprintf('%s/%s/%s', $this->baseUrl(), $this->token(), $method);
    }

    public function sendDocument(int|string $chatId, string $document, array $options = []): array
    {
        return $this->sendFile($chatId, $document, $options);
    }

    /**
     * Native Eitaa file endpoint. Accepts a file id or a public URL.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendFile(int|string $chatId, string $file, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_DOCUMENT);

        $payload = $options;
        $payload['chat_id'] = $this->normalizeChatId($chatId);
        $payload['file'] = $this->requireNonEmpty($file, 'file');

        return $this->call('sendFile', $payload);
    }
}
