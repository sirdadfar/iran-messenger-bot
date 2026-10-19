<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\RubikaAdapter;
use IranMessenger\Exception\ApiException;
use IranMessenger\Exception\UnsupportedFeatureException;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Keyboard\ReplyKeyboard;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class RubikaAdapterTest extends TestCase
{
    public function testFeatureMatrixReflectsRubikaShape(): void
    {
        $bot = new RubikaAdapter('rubika-token', new FakeHttpClient());

        self::assertTrue($bot->supports('send_message'));
        self::assertTrue($bot->supports('photo'));
        self::assertTrue($bot->supports('document'));
        self::assertTrue($bot->supports('reply_keyboard'));
        self::assertTrue($bot->supports('polling'));
        self::assertFalse($bot->supports('inline_keyboard'));
        self::assertFalse($bot->supports('webhook'));
        self::assertFalse($bot->supports('edit_message'));
    }

    public function testSendMessageUsesRubikaUrlAndKeypadShape(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json([
            'status' => 'OK',
            'data' => ['message_id' => 'mid-1'],
        ])]);
        $bot = new RubikaAdapter('rubika-token', $http);

        $keyboard = ReplyKeyboard::make()
            ->row(['بله', 'خیر'])
            ->resize();

        $response = $bot->sendMessage('b0d1', 'سلام', ['keyboard' => $keyboard]);

        $request = $http->lastRequest();

        self::assertSame(
            'https://botapi.rubika.ir/v3/rubika-token/sendMessage',
            $request['url'],
        );
        self::assertSame('b0d1', $request['payload']['chat_id']);
        self::assertSame('سلام', $request['payload']['text']);
        self::assertSame([
            'rows' => [[
                'buttons' => [
                    ['button_text' => 'بله'],
                    ['button_text' => 'خیر'],
                ],
            ]],
        ], $request['payload']['chat_keypad']);
        self::assertSame('New', $request['payload']['chat_keypad_type']);
        self::assertArrayNotHasKey('reply_markup', $request['payload']);
        self::assertSame('mid-1', $response['data']['message_id']);
    }

    public function testInlineKeyboardIsUnsupported(): void
    {
        $bot = new RubikaAdapter('rubika-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->sendMessage('b0d1', 'test', [
            'keyboard' => InlineKeyboard::make()->row([
                InlineKeyboard::callback('Yes', 'yes'),
            ]),
        ]);
    }

    public function testSendPhotoMapsToSendFile(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['status' => 'OK', 'data' => []])]);
        $bot = new RubikaAdapter('rubika-token', $http);

        $bot->sendPhoto('b0d1', 'https://example.com/photo.jpg', ['caption' => 'عکس']);

        $request = $http->lastRequest();

        self::assertStringEndsWith('/sendFile', $request['url']);
        self::assertSame('https://example.com/photo.jpg', $request['payload']['file']);
        self::assertSame('عکس', $request['payload']['caption']);
        self::assertArrayNotHasKey('photo', $request['payload']);
    }

    public function testGetChatsUsesNativeMethod(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::json(['status' => 'OK', 'data' => ['chats' => []]])]);
        $bot = new RubikaAdapter('rubika-token', $http);

        $response = $bot->getChats(['limit' => 10]);

        self::assertSame(
            'https://botapi.rubika.ir/v3/rubika-token/getChats',
            $http->lastRequest()['url'],
        );
        self::assertSame(10, $http->lastRequest()['payload']['limit']);
        self::assertSame([], $response['data']['chats']);
    }

    public function testNonOkStatusThrowsApiException(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::json(['status' => 'INVALID_INPUT', 'message' => 'invalid chat id']),
        ]);
        $bot = new RubikaAdapter('rubika-token', $http);

        try {
            $bot->sendMessage('missing-chat', 'hi');
            self::fail('ApiException was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame('sendMessage', $exception->getMethod());
            self::assertSame('INVALID_INPUT', $exception->getProviderCode());
            self::assertStringContainsString('invalid chat id', $exception->getMessage());
            self::assertSame(200, $exception->getHttpStatus());
        }
    }

    public function testWebhookIsUnsupported(): void
    {
        $bot = new RubikaAdapter('rubika-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->setWebhook('https://example.com/hook');
    }
}
