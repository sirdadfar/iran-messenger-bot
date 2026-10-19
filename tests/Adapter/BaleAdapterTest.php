<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\BaleAdapter;
use IranMessenger\Messenger;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class BaleAdapterTest extends TestCase
{
    public function testSendMessageUsesBaleCompatibleEndpoint(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(['message_id' => 300])]);
        $bot = new BaleAdapter('98765:bale-token', $http);

        $response = $bot->sendMessage(1234, 'سلام بال');

        $request = $http->lastRequest();

        self::assertSame('https://tapi.bale.ai/bot98765:bale-token/sendMessage', $request['url']);
        self::assertSame(1234, $request['payload']['chat_id']);
        self::assertSame('سلام بال', $request['payload']['text']);
        self::assertSame(300, $response['result']['message_id']);
    }

    public function testBaleSupportsTheTelegramFeatureSubset(): void
    {
        $bot = new BaleAdapter('98765:bale-token', new FakeHttpClient());

        self::assertSame('bale', $bot->driver());
        self::assertSame(4096, $bot->maxMessageLength());

        foreach ([
            Messenger::FEATURE_SEND_MESSAGE,
            Messenger::FEATURE_PHOTO,
            Messenger::FEATURE_DOCUMENT,
            Messenger::FEATURE_INLINE_KEYBOARD,
            Messenger::FEATURE_REPLY_KEYBOARD,
            Messenger::FEATURE_EDIT_MESSAGE,
            Messenger::FEATURE_DELETE_MESSAGE,
            Messenger::FEATURE_CALLBACK_QUERY,
            Messenger::FEATURE_WEBHOOK,
            Messenger::FEATURE_POLLING,
            Messenger::FEATURE_GET_ME,
        ] as $feature) {
            self::assertTrue($bot->supports($feature), $feature . ' should be supported');
        }
    }

    public function testEditMessageMapsToEditMessageText(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(true)]);
        $bot = new BaleAdapter('98765:bale-token', $http);

        $bot->editMessage(1, 2, 'new text');

        self::assertStringEndsWith('/editMessageText', $http->lastRequest()['url']);
    }
}
