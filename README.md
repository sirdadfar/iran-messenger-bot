# iran-messenger-bot

[نسخهٔ فارسی (primary)](README.fa.md) · [Security](SECURITY.md) · [Changelog](CHANGELOG.md)

A framework-agnostic PHP library for building bots on Iranian and regional messengers: **Telegram, Bale, Eitaa and Rubika**.

The library gives you one small `Messenger` contract, immutable DTOs, fluent keyboards and adapters that own the platform-specific details. No Laravel, WordPress or Guzzle dependency is required.

> This is an open-source library, not a hosted service. You still need an account and a bot token from each platform.

## The problem

Building the same bot for four messengers usually means four payload shapes, four error formats and four keyboard dialects. `iran-messenger-bot` keeps your application code on one API:

- One `Messenger` interface: send, edit, delete, callback answers, polling and webhooks.
- Typed, immutable updates: `Update`, `Message`, `User`, `Chat`, `CallbackQuery`, `PhotoSize`.
- Fluent inline and reply keyboards.
- Provider errors mapped to `ApiException`; bot tokens redacted from messages, logs and stored context.
- Word-boundary splitting for long messages.
- Honest feature detection via `supports()` and `UnsupportedFeatureException`.

## Requirements

- PHP 8.1+
- `curl`, `json` and `mbstring` extensions
- Composer

## Installation

```bash
composer require sirdadfar/iran-messenger-bot
```

If the package is not on Packagist yet, use a path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../iran-messenger-bot" }
    ],
    "require": {
        "sirdadfar/iran-messenger-bot": "*"
    }
}
```

## Quick start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use IranMessenger\MessengerFactory;

$bot = MessengerFactory::make('telegram', '123456:ABC-DEF...');

$bot->sendMessage(123456789, 'Hello from iran-messenger-bot!');
```

Supported drivers: `telegram`, `bale`, `eitaa`, `rubika`. Driver names are case-insensitive.

### Options

```php
use IranMessenger\Http\CurlClient;
use IranMessenger\MessengerFactory;

$bot = MessengerFactory::make('telegram', $token, [
    'base_url' => 'https://api.telegram.org', // local Bot API server or proxy
    'timeout' => 20,
    'connect_timeout' => 5,
    'max_message_length' => 4096,
    'methods' => [
        'editMessage' => 'editMessageText',
    ],
    'http_client' => new CurlClient(20),
]);
```

## Keyboards

### Inline keyboard

```php
use IranMessenger\Keyboard\InlineKeyboard;

$keyboard = InlineKeyboard::make()
    ->row([
        InlineKeyboard::callback('✅ Confirm', 'confirm:42'),
        InlineKeyboard::url('📚 Docs', 'https://example.com/help'),
    ])
    ->row([
        InlineKeyboard::callback('❌ Cancel', 'cancel:42'),
    ]);

$bot->sendMessage(123456789, 'Confirm your order:', [
    'keyboard' => $keyboard,
    'parse_mode' => 'HTML',
]);
```

`toArray()` produces exactly:

```php
[
    'inline_keyboard' => [
        [
            ['text' => '✅ Confirm', 'callback_data' => 'confirm:42'],
            ['text' => '📚 Docs', 'url' => 'https://example.com/help'],
        ],
        [
            ['text' => '❌ Cancel', 'callback_data' => 'cancel:42'],
        ],
    ],
]
```

Callback data is limited to 64 bytes. Each button must contain exactly one of `callback_data` or `url`.

### Reply keyboard

```php
use IranMessenger\Keyboard\ReplyKeyboard;

$menu = ReplyKeyboard::make()
    ->row(['📦 Orders', '👤 Account'])
    ->row([['text' => '☎️ Share phone', 'request_contact' => true]])
    ->resize()
    ->oneTime()
    ->selective();

$bot->sendMessage(123456789, 'Choose an option:', [
    'keyboard' => $menu,
]);
```

Both keyboard classes are immutable: every method returns a new instance. Disable a flag again with `->resize(false)`, `->oneTime(false)` or `->selective(false)`.

## Drivers

### Telegram

```php
$bot = MessengerFactory::make('telegram', $token);

$bot->sendMessage($chatId, 'Hello from Telegram');
$bot->sendPhoto($chatId, 'https://example.com/photo.jpg', ['caption' => 'Photo']);
$bot->sendDocument($chatId, 'https://example.com/report.pdf');
$bot->editMessage($chatId, $messageId, 'Edited text');
$bot->deleteMessage($chatId, $messageId);
```

Base URL: `https://api.telegram.org/bot{token}/{method}`. Get a token from [@BotFather](https://t.me/BotFather).

### Bale

```php
$bot = MessengerFactory::make('bale', $token);

$bot->sendMessage($chatId, 'Hello from Bale');
$bot->sendPhoto($chatId, 'https://example.com/photo.jpg');
```

Base URL: `https://tapi.bale.ai/bot{token}/{method}`. Bale exposes a Telegram-compatible subset. Get a token from BotFather inside Bale.

### Eitaa

```php
use IranMessenger\Adapter\EitaaAdapter;

/** @var EitaaAdapter $bot */
$bot = MessengerFactory::make('eitaa', $token);

$bot->sendMessage($chatId, 'Hello from Eitaa');
$bot->sendFile($chatId, 'https://example.com/report.pdf', ['caption' => 'Report']);

// Same as sendFile:
$bot->sendDocument($chatId, 'https://example.com/report.pdf');
```

Base URL: `https://eitaayar.ir/api/{token}/{method}`. Eitaa has a limited, send-oriented API: inline keyboards, editing, deletion, callbacks and webhooks are not available. `supports('inline_keyboard')` returns `false` and using one throws `UnsupportedFeatureException`.

### Rubika

```php
use IranMessenger\Adapter\RubikaAdapter;

/** @var RubikaAdapter $bot */
$bot = MessengerFactory::make('rubika', $token);

$chats = $bot->getChats(); // Rubika-specific method
$bot->sendMessage($chatId, 'Hello from Rubika');
$bot->sendFile($chatId, 'https://example.com/file.pdf');
```

Base URL: `https://botapi.rubika.ir/v3/{token}/{method}`. Rubika uses `chat_keypad` / `chat_keypad_type` instead of `reply_markup`; the adapter performs the conversion. Inline keyboards are not part of the public v3 shape.

> The exact method set and `chat_keypad` object may differ between Rubika API versions. The adapter targets the public v3 shape. Verify `base_url` and, if needed, `methods` against your account before production use.

## Webhook

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use IranMessenger\Exception\ValidationException;
use IranMessenger\MessengerFactory;
use IranMessenger\Support\Webhook;

$bot = MessengerFactory::make('telegram', (string) getenv('TELEGRAM_BOT_TOKEN'));

// 1) Verify the webhook secret before trusting the body. The comparison is
//    constant-time and accepts HTTP_X_... or x-... header spellings.
$secret = (string) getenv('TELEGRAM_WEBHOOK_SECRET');

if ($secret !== '' && !Webhook::verifyServerHeader($_SERVER, $secret)) {
    http_response_code(403);
    exit;
}

// 2) Read the body with a 1 MiB cap and strict JSON parsing.
try {
    $update = Webhook::update((string) file_get_contents('php://input'));
} catch (ValidationException) {
    http_response_code(400);
    exit;
}

if ($update->message !== null && $update->message->text === '/start') {
    $bot->sendMessage($update->message->chat->id, 'Welcome!');
}

if ($update->callbackQuery !== null) {
    $callback = $update->callbackQuery;

    $bot->answerCallbackQuery($callback->id, ['text' => 'Saved ✅']);

    if ($callback->message !== null) {
        $bot->editMessage(
            $callback->message->chat->id,
            $callback->message->messageId,
            'Confirmed ✅',
        );
    }
}

http_response_code(200);
```

Register the webhook:

```php
$bot->setWebhook('https://example.com/bot/webhook.php', [
    'secret_token' => $webhookSecret,
    'allowed_updates' => ['message', 'callback_query'],
]);
```

Remove it:

```php
$bot->deleteWebhook(['drop_pending_updates' => true]);
```

> Use a public HTTPS URL. Bale may use a different webhook security header; verify it against the official documentation.

### Secret generation and limits

```php
// 32 random bytes, URL-safe and valid as a Telegram secret_token.
$webhookSecret = IranMessenger\Support\Webhook::generateSecret();
```

- `Webhook::verifyServerHeader($_SERVER, $secret)` rejects an empty or wrong header.
- `Webhook::decode()` rejects bodies over 1 MiB, invalid JSON and non-object JSON (a string or a number) with `ValidationException`.
- `Webhook::update()` does both steps in one call.
- If a platform has no secret header, put a random token in the URL path and compare it with `Webhook::verify()`; this library cannot create a signature the provider does not send.

## Polling

```php
$offset = 0;

while (true) {
    $response = $bot->getUpdates([
        'offset' => $offset,
        'timeout' => 30,
        'allowed_updates' => ['message', 'callback_query'],
    ]);

    foreach ($response['result'] ?? [] as $rawUpdate) {
        $update = $bot->parseUpdate($rawUpdate);

        if ($update->message?->isCommand()) {
            $bot->sendMessage($update->message->chat->id, 'Command received.');
        }

        $offset = $update->updateId + 1;
    }
}
```

`getUpdates()` returns the full provider envelope; updates live under `result`. See the support matrix for Eitaa and Rubika.

## Long messages

`sendMessage()` automatically splits text at newlines or spaces (hard-cutting only an unbroken word) and sends the chunks in order. A keyboard and `reply_to_message_id` are attached to the first chunk only.

```php
$responses = $bot->sendMessageChunked($chatId, $longText); // all responses

$first = $bot->sendMessage($chatId, $longText); // first response; all chunks are sent
```

Split manually:

```php
$chunks = $bot->splitMessage($longText);
$chunks = $bot->splitMessage($longText, 1000);
```

Defaults: Telegram and Bale 4096, Eitaa 1000 (configurable), Rubika 4096.

## Errors and security

```php
use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\NetworkException;
use IranMessenger\Exception\UnsupportedFeatureException;
use IranMessenger\Exception\ValidationException;

try {
    $bot->sendMessage($chatId, 'Hello');
} catch (ApiException $exception) {
    logger()->error((string) $exception, [
        'method' => $exception->getMethod(),
        'http_status' => $exception->getHttpStatus(),
        'provider_code' => $exception->getProviderCode(),
    ]);
} catch (ValidationException $exception) {
    // Invalid input; no request was sent.
} catch (NetworkException $exception) {
    // cURL/transport failure; the token-bearing URL is not in the message.
} catch (UnsupportedFeatureException $exception) {
    // For example, an inline keyboard on Eitaa.
}
```

- `ApiException` covers non-2xx responses, `{"ok": false}` and non-OK Rubika statuses. It exposes `getMethod()`, `getPayload()`, `getResponse()`, `getHttpStatus()` and `getProviderCode()`.
- `ValidationException` before any request for empty tokens/text, zero ids, invalid URLs and similar input.
- `NetworkException` for cURL/transport failures; its message never includes the token-bearing URL.
- `UnsupportedFeatureException` when a platform lacks a capability.
- Bot tokens are redacted from exception messages, `__toString()`, stored payloads and stored responses.

**Never commit a bot token or send it to a browser.** Use environment variables:

```bash
export TELEGRAM_BOT_TOKEN="123456:ABC..."
```

Restrict access to application logs in production.

## Laravel (optional)

The core has no Laravel dependency. When `illuminate/support` is installed, the optional provider and facade are auto-discovered.

```bash
php artisan vendor:publish --tag=iran-messenger-config
```

`.env`:

```dotenv
IRAN_MESSENGER_DRIVER=telegram
IRAN_MESSENGER_TOKEN=123456:ABC...
```

Facade:

```php
use IranMessenger\Laravel\Messenger;

Messenger::sendMessage($chatId, 'Hello from Laravel');
```

Injection:

```php
use IranMessenger\Messenger as Bot;

final class WelcomeController
{
    public function __construct(private readonly Bot $bot)
    {
    }

    public function __invoke(): void
    {
        $this->bot->sendMessage(123456789, 'Welcome');
    }
}
```

If auto-discovery is disabled, register `IranMessenger\Laravel\MessengerServiceProvider` in `config/app.php` and alias `IranMessenger` to `IranMessenger\Laravel\Messenger`.

## Feature support matrix

| Feature | Telegram | Bale | Eitaa | Rubika |
| --- | --- | --- | --- | --- |
| `sendMessage` | ✅ | ✅ | ✅ | ✅ |
| `sendPhoto` | ✅ | ✅ | ❌ (use `sendFile`) | ✅ (via `sendFile`) |
| `sendDocument` | ✅ | ✅ | ✅ (via `sendFile`) | ✅ (via `sendFile`) |
| Inline keyboard | ✅ | ✅ | ❌ | ❌ |
| Reply keyboard | ✅ | ✅ | ❌ | ✅ (`chat_keypad`) |
| `editMessage` | ✅ | ✅ | ❌ | ❌ |
| `deleteMessage` | ✅ | ✅ | ❌ | ❌ |
| `answerCallbackQuery` | ✅ | ✅ | ❌ | ❌ |
| Webhook | ✅ | ✅ | ❌ | ❌ |
| Polling (`getUpdates`) | ✅ | ✅ | ❌ | ✅ |
| `getMe` | ✅ | ✅ | ✅ | ✅ |
| `getChats` | ❌ | ❌ | ❌ | ✅ (adapter-specific) |

Check at runtime:

```php
$bot->supports('inline_keyboard'); // true or false
```

## Status and limitations

This library is unit-tested against a fake `HttpClient`. **It has not been tested against live accounts on any of the four platforms**, and we make no claim of live verification.

- **No multipart/local file upload.** Every request is JSON. Pass an existing `file_id` or a public URL to `sendPhoto` / `sendDocument`. Multipart upload is not implemented.
- Eitaa's EitaaYar API is send-oriented. Method names and the base URL follow the public shape; adjust `base_url` and `methods` if your account exposes a different version.
- Rubika's `chat_keypad` shape follows the public v3 API and may require extra fields depending on your account/version.
- Bale covers the Telegram-compatible subset; some Telegram methods may not exist.
- Albums/media groups, concurrent uploads, queues, automatic retries and internal rate limiting are out of scope.
- Message length is measured with `mb_strlen` (Unicode code points). Some providers count emoji as two units; lower `max_message_length` for emoji-heavy text.
- The Laravel glue is optional and is not covered by the unit test suite because `illuminate/support` is not a test dependency.

See [docs/platforms-fa.md](docs/platforms-fa.md) (Persian) for platform quirks, token sources and limits.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Current status: **90 tests, 369 assertions**, all passing. CI also runs `composer validate --strict`, `composer audit` and `php -l` on every PHP file (PHP 8.1–8.4 matrix).

## Contributing

- Open an issue or pull request with the real provider behavior you observed.
- Keep tests and both READMEs in sync with API changes.
- Never paste real tokens, phone numbers or user data into an issue.

## License

MIT © 2026 sirdadfar. See [LICENSE](LICENSE).
