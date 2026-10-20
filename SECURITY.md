# Security policy — iran-messenger-bot

This document covers the PHP library in this repository. It does not cover your bot account, your
webhook server, your TLS termination, your framework or the provider APIs themselves.

Persian documentation starts at [`README.fa.md`](README.fa.md); per-platform notes are in
[`docs/platforms-fa.md`](docs/platforms-fa.md).

## Supported versions

| Version | PHP | Security fixes |
| --- | --- | --- |
| `1.1.x` | 8.1, 8.2, 8.3 | Supported |
| `1.0.x` | 8.1, 8.2, 8.3 | Not supported; upgrade to 1.1.x |

## Reporting a vulnerability

Use **GitHub Security Advisories**:

<https://github.com/sirdadfar/iran-messenger-bot/security/advisories/new>

Do **not** open a public issue for a suspected vulnerability. Never include a live bot token, a
webhook secret, customer messages or a production webhook URL in a report. A minimal reproduction
built on `FakeHttpClient` (see `tests/Fake/FakeHttpClient.php`) is ideal, because it needs no real
credentials.

This is a single-maintainer portfolio project. There is no SLA and no bounty. If you need
contractual response times, arrange your own review or support agreement.

## In scope

- The library code under `src/`, the configuration file and the Laravel integration.
- Credential handling: token storage in memory, token redaction in exceptions and `__toString()`.
- Transport behaviour: TLS verification, redirect handling, protocol allowlist, timeouts.
- Webhook helpers: secret comparison, header lookup, body size limits and JSON parsing.
- Request construction: chat ids, message ids, endpoint paths and method overrides.
- The documented behaviour of long-message splitting and feature guards.

## Out of scope

- Telegram, Bale, Eitaa or Rubika infrastructure, rate limits, bans and account policy.
- Your webhook endpoint, reverse proxy, firewall, WAF or TLS certificate.
- Misconfiguration such as committing a token, using `http://` in production, or disabling
  certificate verification outside this library.
- Third-party frameworks, PSR-7 implementations or queue systems you integrate with.
- Content moderation of the messages your bot sends.

## Security design

### The bot token is a password

A Bot API token grants full control of the bot: it can read updates, send messages as the bot and
change the webhook. The library therefore treats it as a secret:

- Every provider URL contains the token (`/bot{token}/method`), which is how the providers work. The
  library never logs request URLs by itself, and `ApiException` scrubs the token and the
  `bot<digits>:<secret>` pattern from messages, `__toString()`, stored payloads and stored
  responses.
- `AbstractAdapter::__toString()` prints `token=[redacted]`.
- `Http\CurlClient` scrubs the token from cURL error strings, which can contain the request URL.
- Empty tokens and tokens containing whitespace are rejected in the constructor.
- Tokens are stored in a `readonly` property; there is no getter that returns them publicly.

Store the token in an environment variable or a secrets manager. Do not commit it, do not put it in
a query string of your own endpoint, and rotate it in the provider's bot settings if it leaks.

### Webhook endpoints are public

Anyone who learns the URL can post an update. The library provides the pieces to verify the
request, but it cannot enforce them for you:

- `Support\Webhook::verifyServerHeader($_SERVER, $secret)` compares the provider header with
  `hash_equals()`; an empty secret never matches. The header lookup is case-insensitive and accepts
  `HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN`, `x-telegram-bot-api-secret-token` and similar spellings.
- `Support\Webhook::decode()` caps the body at 1 MiB (configurable), requires valid JSON with a depth
  limit of 64 and rejects non-object bodies. Rejecting oversized bodies before `json_decode()`
  prevents memory and CPU abuse.
- `Support\Webhook::update()` combines decoding and `Update::fromArray()`.
- `Support\Webhook::generateSecret()` returns 32 random bytes as a URL-safe string that satisfies
  the provider's `secret_token` rules.
- Telemetry-free: the library makes no request other than the Bot API call you asked for.

Eitaa and Rubika do not document a webhook secret header for every API version. Put a random token
in the webhook path and compare it with `Webhook::verify()`; a library cannot create a signature
that the provider does not send. Verify the current behaviour against the official documentation
for the API version enabled on your account.

### Transport

- TLS certificate and host verification are pinned (`CURLOPT_SSL_VERIFYPEER`,
  `CURLOPT_SSL_VERIFYHOST = 2`), even if the host application changed the global defaults.
- Redirects are disabled, so an `Authorization`-style header or a token in the path cannot be
  forwarded to another host.
- Only `http://` and `https://` are allowed, both in `CurlClient` and via `CURLOPT_PROTOCOLS`.
- Connect and total timeouts are configurable and validated to be at least one second; the default
  is 10 s to connect and 30 s total.
- The webhook URL passed to `setWebhook()` must be a valid absolute HTTP(S) URL.

### Request construction

- Chat ids, message ids and required strings are validated before a request is built.
- The `base_url` option must be an absolute `http://` or `https://` URL and must not embed a
  username or password.
- Provider method overrides (`methods` option) must be alphanumeric identifiers, so they cannot move
  the request to `../` or add a query string.
- Keyboards are converted to arrays; no string interpolation of user data into URLs or headers.

### Attacker-controlled payloads

Webhook bodies are untrusted input. DTO factories read scalar fields through `Support\Value`, so an
array or object in a string field is ignored instead of raising "Array to string conversion" (which
Laravel converts to an `ErrorException`) or a `TypeError`. Numeric ids may arrive as numeric strings.
Missing required fields (`update_id`, `message_id`, `chat`, `callback_query.id`,
`callback_query.from`) throw `ValidationException`.

## Known gaps and residual risk

- **No live provider verification.** All tests use `FakeHttpClient`. The library has not been run
  against live Telegram, Bale, Eitaa or Rubika accounts in CI.
- **No webhook signature scheme for Eitaa/Rubika.** See the note above; use a secret path token.
- **No automatic handling of `429` / `retry_after`.** A rate-limited call returns an `ApiException`;
  your application must back off and retry. The library does not retry on its own, which avoids
  duplicate sends but pushes the policy to you.
- **No idempotency store for updates.** A provider may deliver the same update twice. Deduplicate on
  `Update::$updateId` in your application.
- **In-memory secrets only.** The token lives in memory for the process lifetime; the library has no
  key rotation, encryption at rest or audit log.
- **No message content filtering.** What your bot sends and to whom is your responsibility.
- **Local file uploads are not implemented.** Pass a `file_id` or a public URL; do not build a
  multipart helper that trusts a user-supplied path.

## Dependency and CI posture

- `composer audit` runs in CI on every push (see `.github/workflows/tests.yml`).
- The test suite runs on PHP 8.1, 8.2 and 8.3.
- `failOnWarning`, `failOnRisky` and `beStrictAboutOutputDuringTests` are enabled, so noisy tests do
  not pass silently.
