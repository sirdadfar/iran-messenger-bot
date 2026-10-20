# Changelog

All notable changes to `iran-messenger-bot` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-10-21

### Added

- `Support\Webhook`: `verify()`, `verifyServerHeader()`, `header()`, `isValidSecret()`, `generateSecret()`, `decode()` and `update()`. The secret is compared with `hash_equals()`, the header lookup is case-insensitive and accepts both `HTTP_X_...` and `x-...` spellings, and bodies are capped at 1 MiB.
- `Support\Value`: defensive scalar access so array or object values in an attacker-controlled webhook payload cannot trigger "Array to string conversion" or a `TypeError` in the DTO factories.
- `tests/Support/WebhookTest.php`, `tests/Dto/MalformedPayloadTest.php` and `tests/Adapter/AdapterHardeningTest.php`.

### Changed

- `base_url` must be an absolute `http://` or `https://` URL without embedded credentials.
- Provider method overrides (`methods` option) must be alphanumeric identifiers, so they cannot escape the `/bot{token}/` path.
- `Http\CurlClient` pins `CURLOPT_SSL_VERIFYPEER`, `CURLOPT_SSL_VERIFYHOST=2` and the HTTP/HTTPS protocol allowlist, and rejects non-HTTP URLs before `curl_init()`.
- README (both languages) documents webhook hardening with the new helpers.

## [1.0.0] - 2026-10-20

### Added

- Framework-agnostic `IranMessenger\Messenger` contract with send, edit, delete, callback, polling and webhook methods.
- `MessengerFactory` for the `telegram`, `bale`, `eitaa` and `rubika` drivers.
- Telegram adapter built against the public Bot API shape.
- Bale adapter for the Telegram-compatible `tapi.bale.ai` API.
- Eitaa adapter for the limited EitaaYar API, including honest `supports()` reporting and `UnsupportedFeatureException` for inline keyboards.
- Rubika adapter for the Bot API v3 shape, including `getChats()` and `chat_keypad` conversion.
- Immutable DTOs: `Update`, `Message`, `User`, `Chat`, `CallbackQuery`, `PhotoSize`.
- Immutable fluent keyboards: `InlineKeyboard` and `ReplyKeyboard`.
- `Http\HttpClient` abstraction with a cURL implementation and a reusable `FakeHttpClient` for tests.
- `ApiException`, `ValidationException`, `UnsupportedFeatureException` and `NetworkException`.
- Automatic token redaction in exception messages, `__toString()` output and stored exception context.
- Word-boundary long-message splitting with platform-specific limits and `sendMessageChunked()`.
- Optional Laravel service provider, facade, publishable config and auto-discovery metadata.
- English and Persian documentation, platform notes, CI matrix for PHP 8.1/8.2/8.3, and 63 unit tests.

### Notes

- The library is tested against a fake HTTP client. It has not been verified against live provider accounts.
- Local multipart file upload is not implemented; pass a `file_id` or a public URL instead.
- Eitaa and Rubika base URLs/method names should be verified for the API version enabled on your account.
