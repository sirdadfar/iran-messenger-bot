<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\EitaaAdapter;
use IranMessenger\Exception\UnsupportedFeatureException;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class EitaaAdapterTest extends TestCase
{
    public function testFeatureMatrixIsHonestAboutEitaaLimits(): void
    {
        $bot = new EitaaAdapter('eitaa-token', new FakeHttpClient());

        self::assertTrue($bot->supports('send_message'));
        self::assertTrue($bot->supports('document'));
        self::assertTrue($bot->supports('get_me'));
        self::assertFalse($bot->supports('inline_keyboard'));
        self::assertFalse($bot->supports('reply_keyboard'));
        self::assertFalse($bot->supports('edit_message'));
        self::assertFalse($bot->supports('webhook'));
        self::assertSame(1000, $bot->maxMessageLength());
    }

    public function testInlineKeyboardThrowsUnsupportedFeatureException(): void
    {
        $bot = new EitaaAdapter('eitaa-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->sendMessage(123, 'test', [
            'keyboard' => InlineKeyboard::make()->row([
                InlineKeyboard::callback('Yes', 'yes'),
            ]),
        ]);
    }

    public function testRawInlineKeyboardArrayAlsoThrows(): void
    {
        $bot = new EitaaAdapter('eitaa-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->sendMessage(123, 'test', [
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Yes', 'callback_data' => 'yes']]]],
        ]);
    }

    public function testSendFileUsesEitaaUrlAndPayload(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(['message_id' => 9001])]);
        $bot = new EitaaAdapter('eitaa-token', $http);

        $response = $bot->sendFile('@mychannel', 'https://example.com/report.pdf', ['caption' => 'گزارش']);

        $request = $http->lastRequest();

        self::assertSame('https://eitaayar.ir/api/eitaa-token/sendFile', $request['url']);
        self::assertSame('@mychannel', $request['payload']['chat_id']);
        self::assertSame('https://example.com/report.pdf', $request['payload']['file']);
        self::assertSame('گزارش', $request['payload']['caption']);
        self::assertSame(9001, $response['result']['message_id']);
    }

    public function testSendDocumentMapsToSendFile(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(['message_id' => 9002])]);
        $bot = new EitaaAdapter('eitaa-token', $http);

        $bot->sendDocument(55, 'file-id-123');

        self::assertStringEndsWith('/sendFile', $http->lastRequest()['url']);
        self::assertSame('file-id-123', $http->lastRequest()['payload']['file']);
        self::assertArrayNotHasKey('document', $http->lastRequest()['payload']);
    }

    public function testSendPhotoIsUnsupported(): void
    {
        $bot = new EitaaAdapter('eitaa-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->sendPhoto(55, 'https://example.com/photo.jpg');
    }

    public function testPollingIsUnsupported(): void
    {
        $bot = new EitaaAdapter('eitaa-token', new FakeHttpClient());

        $this->expectException(UnsupportedFeatureException::class);

        $bot->getUpdates();
    }

    public function testCustomBaseUrlIsConfigurable(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(true)]);
        $bot = new EitaaAdapter('token-1', $http, ['base_url' => 'https://eitaa-proxy.test/api']);

        $bot->sendMessage(10, 'hi');

        self::assertSame('https://eitaa-proxy.test/api/token-1/sendMessage', $http->lastRequest()['url']);
    }
}
