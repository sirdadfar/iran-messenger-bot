<?php

declare(strict_types=1);

namespace IranMessenger\Adapter;

use IranMessenger\Dto\Update;
use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\UnsupportedFeatureException;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Http\CurlClient;
use IranMessenger\Http\HttpClient;
use IranMessenger\Http\Response;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Keyboard\ReplyKeyboard;
use IranMessenger\Messenger;

/**
 * Shared behaviour for every adapter: token handling, endpoint building,
 * payload normalisation, keyboard guards, error mapping and message chunking.
 */
abstract class AbstractAdapter implements Messenger
{
    private readonly HttpClient $http;

    private readonly string $token;

    /** @var array<string, mixed> */
    private readonly array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $token, ?HttpClient $http = null, array $options = [])
    {
        $token = trim($token);

        if ($token === '') {
            throw new ValidationException('Bot token must not be empty.');
        }

        if (preg_match('/\s/', $token) === 1) {
            throw new ValidationException('Bot token must not contain whitespace.');
        }

        $this->token = $token;
        $this->options = $options;
        $this->http = $http ?? new CurlClient(
            timeout: (int) ($options['timeout'] ?? 30),
            connectTimeout: (int) ($options['connect_timeout'] ?? 10),
        );
    }

    abstract public function driver(): string;

    abstract protected function defaultBaseUrl(): string;

    /**
     * @return list<string>
     */
    abstract protected function features(): array;

    abstract protected function defaultMaxMessageLength(): int;

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    public function maxMessageLength(): int
    {
        $limit = $this->options['max_message_length'] ?? $this->defaultMaxMessageLength();

        if (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }

        if (!is_int($limit) || $limit < 1) {
            throw new ValidationException('The "max_message_length" option must be a positive integer.');
        }

        return $limit;
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $responses = $this->sendMessageChunked($chatId, $text, $options);

        // sendMessageChunked() always returns at least one response for a
        // non-empty message, so the first element is safe to access.
        return $responses[0];
    }

    /**
     * Send a long message in platform-sized chunks and return every provider
     * response. `sendMessage()` calls this and returns the first response.
     *
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function sendMessageChunked(int|string $chatId, string $text, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_SEND_MESSAGE);

        $chatId = $this->normalizeChatId($chatId);

        if (trim($text) === '') {
            throw new ValidationException('Message text must not be empty.');
        }

        $responses = [];

        foreach ($this->splitMessage($text) as $index => $chunk) {
            $payload = $options;

            // A keyboard and a reply target belong to the first chunk only.
            if ($index > 0) {
                unset($payload['keyboard'], $payload['reply_markup'], $payload['reply_to_message_id']);
            }

            $payload['chat_id'] = $chatId;
            $payload['text'] = $chunk;

            $responses[] = $this->call('sendMessage', $payload);
        }

        return $responses;
    }

    public function sendPhoto(int|string $chatId, string $photo, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_PHOTO);

        $payload = $options;
        $payload['chat_id'] = $this->normalizeChatId($chatId);
        $payload['photo'] = $this->requireNonEmpty($photo, 'photo');

        return $this->call('sendPhoto', $payload);
    }

    public function sendDocument(int|string $chatId, string $document, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_DOCUMENT);

        $payload = $options;
        $payload['chat_id'] = $this->normalizeChatId($chatId);
        $payload['document'] = $this->requireNonEmpty($document, 'document');

        return $this->call('sendDocument', $payload);
    }

    public function editMessage(int|string $chatId, int|string $messageId, string $text, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_EDIT_MESSAGE);

        $payload = $options;
        $payload['chat_id'] = $this->normalizeChatId($chatId);
        $payload['message_id'] = $this->normalizeMessageId($messageId);
        $payload['text'] = $this->requireNonEmpty($text, 'text');

        return $this->call('editMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_CALLBACK_QUERY);

        $payload = $options;
        $payload['callback_query_id'] = $this->requireNonEmpty($callbackQueryId, 'callbackQueryId');

        return $this->call('answerCallbackQuery', $payload);
    }

    public function deleteMessage(int|string $chatId, int|string $messageId): array
    {
        $this->assertFeature(self::FEATURE_DELETE_MESSAGE);

        return $this->call('deleteMessage', [
            'chat_id' => $this->normalizeChatId($chatId),
            'message_id' => $this->normalizeMessageId($messageId),
        ]);
    }

    public function getUpdates(array $options = []): array
    {
        $this->assertFeature(self::FEATURE_POLLING);

        return $this->call('getUpdates', $options);
    }

    public function setWebhook(string $url, array $options = []): array
    {
        $this->assertFeature(self::FEATURE_WEBHOOK);

        $url = trim($url);

        if (
            preg_match('~^https?://~i', $url) !== 1
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            throw new ValidationException('Webhook URL must be a valid http:// or https:// URL.');
        }

        $payload = $options;
        $payload['url'] = $url;

        return $this->call('setWebhook', $payload);
    }

    public function deleteWebhook(array $options = []): array
    {
        $this->assertFeature(self::FEATURE_WEBHOOK);

        return $this->call('deleteWebhook', $options);
    }

    public function getMe(): array
    {
        $this->assertFeature(self::FEATURE_GET_ME);

        return $this->call('getMe');
    }

    /**
     * Parse a raw update from a webhook or from `getUpdates()['result']`.
     *
     * @param array<string, mixed> $payload
     */
    public function parseUpdate(array $payload): Update
    {
        return Update::fromArray($payload);
    }

    /**
     * Split text on word boundaries without exceeding the platform limit.
     *
     * Newlines are preferred, then spaces, and a hard cut is used only for a
     * single "word" longer than the limit (for example a huge URL).
     *
     * @return list<string>
     */
    public function splitMessage(string $text, ?int $limit = null): array
    {
        $limit ??= $this->maxMessageLength();

        if ($limit < 1) {
            throw new ValidationException('Message limit must be at least 1 character.');
        }

        if (trim($text) === '') {
            throw new ValidationException('Message text must not be empty.');
        }

        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;
        $minimumCut = max(1, (int) floor($limit / 2));

        while (mb_strlen($remaining, 'UTF-8') > $limit) {
            $window = mb_substr($remaining, 0, $limit, 'UTF-8');

            $cut = mb_strrpos($window, "\n", 0, 'UTF-8');

            if ($cut === false || $cut < $minimumCut) {
                $cut = mb_strrpos($window, ' ', 0, 'UTF-8');
            }

            if ($cut === false || $cut < $minimumCut) {
                $cut = $limit;
            }

            $chunk = rtrim(mb_substr($remaining, 0, $cut, 'UTF-8'));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $remaining = ltrim(mb_substr($remaining, $cut, null, 'UTF-8'));

            if ($remaining === '') {
                break;
            }
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    /**
     * Redacted string representation, safe for logs.
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(driver=%s, base_url=%s, token=[redacted])',
            static::class,
            $this->driver(),
            str_replace($this->token, '[redacted]', $this->baseUrl()),
        );
    }

    /**
     * Send a prepared payload and return the decoded provider envelope.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function call(string $logicalMethod, array $payload = []): array
    {
        $method = $this->providerMethod($logicalMethod);
        $payload = $this->preparePayload($payload);
        $response = $this->http->post($this->endpoint($method), $payload);
        $data = $response->json();

        if ($data === null) {
            throw new ApiException(
                sprintf('%s API returned invalid JSON (HTTP %d).', $this->driverLabel(), $response->statusCode()),
                $method,
                $payload,
                $response->body(),
                $response->statusCode(),
                null,
                [$this->token],
            );
        }

        $failure = $this->failureDescription($data, $response->statusCode());

        if ($failure !== null) {
            $code = $data['error_code'] ?? $data['code'] ?? $data['status'] ?? null;

            if (is_numeric($code)) {
                $code = (int) $code;
            } elseif (!is_string($code)) {
                $code = null;
            }

            throw new ApiException(
                sprintf('%s API error in %s: %s', $this->driverLabel(), $method, $failure),
                $method,
                $payload,
                $data,
                $response->statusCode(),
                $code,
                [$this->token],
            );
        }

        return $data;
    }

    /**
     * Provider method map: logical method => provider method.
     *
     * @return array<string, string>
     */
    protected function methodMap(): array
    {
        return [];
    }

    protected function providerMethod(string $logicalMethod): string
    {
        $overrides = $this->options['methods'] ?? null;

        if (is_array($overrides) && isset($overrides[$logicalMethod]) && is_string($overrides[$logicalMethod])) {
            $override = trim($overrides[$logicalMethod]);

            if ($override !== '') {
                // The method becomes a path segment of the provider URL, so it
                // must not contain "..", "/" or anything else that could move
                // the request to a different path.
                if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $override) !== 1) {
                    throw new ValidationException(
                        sprintf('Provider method override for "%s" must be an alphanumeric identifier.', $logicalMethod),
                    );
                }

                return $override;
            }
        }

        $map = $this->methodMap();

        return $map[$logicalMethod] ?? $logicalMethod;
    }

    protected function endpoint(string $method): string
    {
        return sprintf('%s/bot%s/%s', $this->baseUrl(), $this->token(), $method);
    }

    protected function token(): string
    {
        return $this->token;
    }

    protected function baseUrl(): string
    {
        $base = $this->options['base_url'] ?? $this->defaultBaseUrl();

        if (!is_string($base) || trim($base) === '') {
            throw new ValidationException('The "base_url" option must be a non-empty string.');
        }

        $base = rtrim(trim($base), '/');
        $parts = parse_url($base);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            throw new ValidationException('The "base_url" option must be an absolute http:// or https:// URL.');
        }

        // Credentials inside the base URL would be silently ignored by cURL
        // (or logged by a proxy) and are never part of a Bot API endpoint.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ValidationException('The "base_url" option must not contain a username or password.');
        }

        return $base;
    }

    /**
     * Return an error description for a failed provider envelope, or null when
     * the response is successful. Rubika overrides this.
     *
     * @param array<string, mixed> $data
     */
    protected function failureDescription(array $data, int $status): ?string
    {
        if ($status >= 200 && $status < 300 && (!array_key_exists('ok', $data) || $data['ok'] !== false)) {
            return null;
        }

        return (string) ($data['description'] ?? $data['message'] ?? sprintf('HTTP %d', $status));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        if (array_key_exists('keyboard', $payload) && array_key_exists('reply_markup', $payload)) {
            throw new ValidationException('Use either "keyboard" or "reply_markup", not both.');
        }

        if (array_key_exists('keyboard', $payload)) {
            $keyboard = $payload['keyboard'];
            unset($payload['keyboard']);
            $payload = $this->applyKeyboard($payload, $keyboard);
        }

        if (array_key_exists('reply_markup', $payload)) {
            $markup = $payload['reply_markup'];
            unset($payload['reply_markup']);
            $payload = $this->applyKeyboard($payload, $markup);
        }

        return $this->stripNulls($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function applyKeyboard(array $payload, mixed $keyboard): array
    {
        if ($keyboard instanceof InlineKeyboard) {
            $this->assertFeature(self::FEATURE_INLINE_KEYBOARD);
            $payload['reply_markup'] = $keyboard->toArray();

            return $payload;
        }

        if ($keyboard instanceof ReplyKeyboard) {
            $this->assertFeature(self::FEATURE_REPLY_KEYBOARD);
            $payload['reply_markup'] = $keyboard->toArray();

            return $payload;
        }

        if (is_array($keyboard)) {
            if (array_key_exists('inline_keyboard', $keyboard)) {
                $this->assertFeature(self::FEATURE_INLINE_KEYBOARD);
            } elseif (array_key_exists('keyboard', $keyboard)) {
                $this->assertFeature(self::FEATURE_REPLY_KEYBOARD);
            } else {
                throw new ValidationException('Keyboard arrays must contain "inline_keyboard" or "keyboard".');
            }

            $payload['reply_markup'] = $keyboard;

            return $payload;
        }

        throw new ValidationException('Keyboard must be an InlineKeyboard, ReplyKeyboard or keyboard array.');
    }

    protected function assertFeature(string $feature): void
    {
        if (!$this->supports($feature)) {
            throw new UnsupportedFeatureException($feature, $this->driver());
        }
    }

    protected function normalizeChatId(int|string $chatId): int|string
    {
        if (is_int($chatId)) {
            if ($chatId === 0) {
                throw new ValidationException('Chat id must not be zero.');
            }

            return $chatId;
        }

        $chatId = trim($chatId);

        if ($chatId === '') {
            throw new ValidationException('Chat id must not be empty.');
        }

        if (preg_match('/\s/', $chatId) === 1) {
            throw new ValidationException('Chat id must not contain whitespace.');
        }

        return $chatId;
    }

    protected function normalizeMessageId(int|string $messageId): int
    {
        if (is_int($messageId)) {
            if ($messageId < 1) {
                throw new ValidationException('Message id must be a positive integer.');
            }

            return $messageId;
        }

        $messageId = trim($messageId);

        if ($messageId === '' || !ctype_digit($messageId) || (int) $messageId < 1) {
            throw new ValidationException('Message id must be a positive integer.');
        }

        return (int) $messageId;
    }

    protected function requireNonEmpty(string $value, string $field): string
    {
        if (trim($value) === '') {
            throw new ValidationException(sprintf('The "%s" value must not be empty.', $field));
        }

        return $value;
    }

    protected function driverLabel(): string
    {
        return ucfirst($this->driver());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function stripNulls(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->stripNulls($value);
            }
        }

        return $payload;
    }
}
