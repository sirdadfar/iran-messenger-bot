<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Http\Response;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class TelegramAdapterTest extends TestCase
{
    private const TOKEN = '123456:TEST-TOKEN';

    public function testSendMessageBuildsUrlAndPayload(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::ok(['message_id' => 77, 'date' => 1760000000, 'text' => 'سلام']),
        ]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        $keyboard = InlineKeyboard::make()
            ->row([InlineKeyboard::callback('بله', 'confirm:yes')]);

        $response = $bot->sendMessage(123456789, 'سلام', [
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => null,
        ]);

        $request = $http->lastRequest();

        self::assertSame('POST', $request['method']);
        self::assertSame(
            'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage',
            $request['url'],
        );
        self::assertSame(123456789, $request['payload']['chat_id']);
        self::assertSame('سلام', $request['payload']['text']);
        self::assertSame('HTML', $request['payload']['parse_mode']);
        self::assertArrayNotHasKey('keyboard', $request['payload']);
        self::assertArrayNotHasKey('disable_web_page_preview', $request['payload']);
        self::assertSame([
            'inline_keyboard' => [[
                ['text' => 'بله', 'callback_data' => 'confirm:yes'],
            ]],
        ], $request['payload']['reply_markup']);
        self::assertSame(77, $response['result']['message_id']);
        self::assertTrue($response['ok']);
    }

    public function testSendPhotoAndDocumentUseProviderFields(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::ok(['message_id' => 78]),
            FakeHttpClient::ok(['message_id' => 79]),
        ]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        $bot->sendPhoto(10, 'https://example.com/a.jpg', ['caption' => 'Photo']);
        $bot->sendDocument(10, 'https://example.com/a.pdf', ['caption' => 'Doc']);

        self::assertSame('https://example.com/a.jpg', $http->requests[0]['payload']['photo']);
        self::assertSame('Photo', $http->requests[0]['payload']['caption']);
        self::assertSame('https://example.com/a.pdf', $http->requests[1]['payload']['document']);
        self::assertSame('Doc', $http->requests[1]['payload']['caption']);
        self::assertSame(2, $http->requestCount());
    }

    public function testEditMessageMapsToEditMessageText(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(true)]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        $bot->editMessage(123, 55, 'متن جدید', [
            'keyboard' => InlineKeyboard::make()->row([
                InlineKeyboard::callback('حذف', 'delete:55'),
            ]),
        ]);

        $request = $http->lastRequest();

        self::assertStringEndsWith('/editMessageText', $request['url']);
        self::assertSame(123, $request['payload']['chat_id']);
        self::assertSame(55, $request['payload']['message_id']);
        self::assertSame('متن جدید', $request['payload']['text']);
        self::assertArrayHasKey('reply_markup', $request['payload']);
    }

    public function testGetMeCallsGetMe(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::ok(['id' => 555, 'is_bot' => true, 'first_name' => 'Test Bot', 'username' => 'test_bot']),
        ]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        $response = $bot->getMe();

        self::assertSame('https://api.telegram.org/bot' . self::TOKEN . '/getMe', $http->lastRequest()['url']);
        self::assertSame([], $http->lastRequest()['payload']);
        self::assertSame('test_bot', $response['result']['username']);
    }

    public function testErrorEnvelopeThrowsApiExceptionWithContext(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found',
            ], 400),
        ]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        try {
            $bot->sendMessage(1, 'hi');
            self::fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame('sendMessage', $exception->getMethod());
            self::assertSame(400, $exception->getHttpStatus());
            self::assertSame(400, $exception->getProviderCode());
            self::assertSame(1, $exception->getPayload()['chat_id']);
            self::assertSame('Bad Request: chat not found', $exception->getResponse()['description']);
            self::assertStringContainsString('chat not found', $exception->getMessage());
        }
    }

    public function testTokenIsRedactedFromExceptionAndStringRepresentation(): void
    {
        $token = '987654:AAHsecretTOKENvalue';
        $http = new FakeHttpClient([
            FakeHttpClient::json([
                'ok' => false,
                'description' => 'Unauthorized for bot ' . $token,
            ], 401),
        ]);
        $bot = new TelegramAdapter($token, $http);

        try {
            $bot->sendMessage(1, 'hi');
            self::fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertStringNotContainsString($token, $exception->getMessage());
            self::assertStringNotContainsString('AAHsecretTOKENvalue', (string) $exception);
            self::assertStringNotContainsString(
                $token,
                (string) json_encode($exception->getResponse(), JSON_UNESCAPED_UNICODE),
            );
            self::assertStringContainsString('[redacted]', $exception->getMessage());
        }

        self::assertStringNotContainsString($token, (string) $bot);
        self::assertStringNotContainsString('AAHsecretTOKENvalue', (string) $bot);
        self::assertStringContainsString('[redacted]', (string) $bot);
    }

    public function testInvalidJsonThrowsApiException(): void
    {
        $http = new FakeHttpClient([new Response(502, '<html>Bad gateway</html>')]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        try {
            $bot->getMe();
            self::fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(502, $exception->getHttpStatus());
            self::assertSame('getMe', $exception->getMethod());
            self::assertStringContainsString('invalid JSON', $exception->getMessage());
            self::assertSame('<html>Bad gateway</html>', $exception->getResponse());
        }
    }

    public function testCustomBaseUrlAndMethodOverride(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(true)]);
        $bot = new TelegramAdapter(self::TOKEN, $http, [
            'base_url' => 'https://bot.local.test/',
            'methods' => ['sendMessage' => 'sendMessageCustom'],
        ]);

        $bot->sendMessage(5, 'hello');

        self::assertSame(
            'https://bot.local.test/bot' . self::TOKEN . '/sendMessageCustom',
            $http->lastRequest()['url'],
        );
    }

    public function testWebhookCallbackAndDeleteMethods(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::ok(true),
            FakeHttpClient::ok(true),
            FakeHttpClient::ok(true),
        ]);
        $bot = new TelegramAdapter(self::TOKEN, $http);

        $bot->setWebhook('https://example.com/hook', ['secret_token' => 'hook-secret']);
        $bot->answerCallbackQuery('cb-1', ['text' => 'ثبت شد', 'show_alert' => true]);
        $bot->deleteMessage(10, 11);

        self::assertSame('https://example.com/hook', $http->requests[0]['payload']['url']);
        self::assertSame('hook-secret', $http->requests[0]['payload']['secret_token']);
        self::assertStringEndsWith('/answerCallbackQuery', $http->requests[1]['url']);
        self::assertSame('cb-1', $http->requests[1]['payload']['callback_query_id']);
        self::assertTrue($http->requests[1]['payload']['show_alert']);
        self::assertStringEndsWith('/deleteMessage', $http->requests[2]['url']);
        self::assertSame(10, $http->requests[2]['payload']['chat_id']);
        self::assertSame(11, $http->requests[2]['payload']['message_id']);
    }

    public function testEmptyMessageIsRejectedBeforeRequest(): void
    {
        $http = new FakeHttpClient();
        $bot = new TelegramAdapter(self::TOKEN, $http);

        try {
            $bot->sendMessage(1, '   ');
            self::fail('ValidationException was not thrown.');
        } catch (ValidationException) {
            self::assertSame(0, $http->requestCount());
        }
    }

    public function testZeroChatIdIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new TelegramAdapter(self::TOKEN, new FakeHttpClient()))->sendMessage(0, 'hi');
    }
}
