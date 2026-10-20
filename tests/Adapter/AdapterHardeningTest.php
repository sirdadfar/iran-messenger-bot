<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Exception\NetworkException;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Http\CurlClient;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Configuration that would send a request somewhere unexpected must fail before
 * the transport is touched.
 */
final class AdapterHardeningTest extends TestCase
{
    private const TOKEN = '123456:TEST-TOKEN';

    public function testBaseUrlWithoutSchemeIsRejected(): void
    {
        $bot = new TelegramAdapter(self::TOKEN, new FakeHttpClient(), ['base_url' => 'api.telegram.org']);

        $this->expectException(ValidationException::class);
        $bot->getMe();
    }

    public function testNonHttpSchemeIsRejected(): void
    {
        $bot = new TelegramAdapter(self::TOKEN, new FakeHttpClient(), ['base_url' => 'ftp://api.telegram.org']);

        $this->expectException(ValidationException::class);
        $bot->getMe();
    }

    public function testCredentialsInBaseUrlAreRejected(): void
    {
        $bot = new TelegramAdapter(
            self::TOKEN,
            new FakeHttpClient(),
            ['base_url' => 'https://user:pass@api.telegram.org'],
        );

        $this->expectException(ValidationException::class);
        $bot->getMe();
    }

    public function testValidCustomBaseUrlIsUsed(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(['ok' => true])]);
        $bot = new TelegramAdapter(self::TOKEN, $http, ['base_url' => 'https://bot.local.test/']);

        $bot->getMe();

        self::assertSame(
            'https://bot.local.test/bot' . self::TOKEN . '/getMe',
            $http->lastRequest()['url'],
        );
    }

    public function testMethodOverrideCannotEscapeThePath(): void
    {
        $bot = new TelegramAdapter(
            self::TOKEN,
            new FakeHttpClient(),
            ['methods' => ['sendMessage' => '../../evil']],
        );

        $this->expectException(ValidationException::class);
        $bot->sendMessage(1, 'hello');
    }

    public function testMethodOverrideWithQueryCharactersIsRejected(): void
    {
        $bot = new TelegramAdapter(
            self::TOKEN,
            new FakeHttpClient(),
            ['methods' => ['sendMessage' => 'sendMessage?x=1']],
        );

        $this->expectException(ValidationException::class);
        $bot->sendMessage(1, 'hello');
    }

    public function testValidMethodOverrideIsAccepted(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(['ok' => true])]);
        $bot = new TelegramAdapter(
            self::TOKEN,
            $http,
            ['methods' => ['sendMessage' => 'sendMessageAlias']],
        );

        $bot->sendMessage(1, 'hello');

        self::assertStringEndsWith('/sendMessageAlias', $http->lastRequest()['url']);
    }

    public function testCurlClientRejectsNonHttpSchemesBeforeConnecting(): void
    {
        $client = new CurlClient();

        $this->expectException(NetworkException::class);
        $client->get('file:///etc/passwd');
    }

    public function testCurlClientRejectsRelativeUrls(): void
    {
        $client = new CurlClient();

        $this->expectException(NetworkException::class);
        $client->post('/bot/sendMessage', ['chat_id' => 1]);
    }
}
