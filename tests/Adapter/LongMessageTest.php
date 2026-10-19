<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Adapter;

use IranMessenger\Adapter\EitaaAdapter;
use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class LongMessageTest extends TestCase
{
    public function testDefaultLimitSplitsPersianTextWithoutCuttingWords(): void
    {
        $bot = new TelegramAdapter('token', new FakeHttpClient());
        $text = rtrim(str_repeat('این یک پیام طولانی برای تست شکستن متن است ', 300));

        $chunks = $bot->splitMessage($text);

        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(4096, mb_strlen($chunk, 'UTF-8'));
            self::assertNotSame('', $chunk);
            self::assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }

        // Every word survived: joining the chunks reproduces the input.
        self::assertSame($text, implode(' ', $chunks));
    }

    public function testCustomLimitIsRespected(): void
    {
        $bot = new TelegramAdapter('token', new FakeHttpClient());
        $text = rtrim(str_repeat('کلمه کوتاه دیگر ', 40));

        $chunks = $bot->splitMessage($text, 60);

        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(60, mb_strlen($chunk, 'UTF-8'));
        }

        self::assertSame($text, implode(' ', $chunks));
    }

    public function testNewlineIsPreferredAsBreakPoint(): void
    {
        $bot = new TelegramAdapter('token', new FakeHttpClient());
        $line = rtrim(str_repeat('الف ', 20));
        $text = $line . "\n" . $line;

        $chunks = $bot->splitMessage($text, 100);

        self::assertCount(2, $chunks);
        self::assertSame($line, $chunks[0]);
        self::assertSame($line, $chunks[1]);
    }

    public function testUnbrokenWordIsHardCutButStillWithinLimit(): void
    {
        $bot = new TelegramAdapter('token', new FakeHttpClient());
        $word = str_repeat('a', 1000);

        $chunks = $bot->splitMessage($word, 100);

        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(100, strlen($chunk));
        }

        self::assertSame($word, implode('', $chunks));
    }

    public function testEitaaLimitCoversPersianCharacters(): void
    {
        $bot = new EitaaAdapter('token', new FakeHttpClient());
        $text = rtrim(str_repeat('سلام دنیا ', 200));

        $chunks = $bot->splitMessage($text);

        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(1000, mb_strlen($chunk, 'UTF-8'));
        }

        self::assertSame($text, implode(' ', $chunks));
        self::assertSame(1000, $bot->maxMessageLength());
    }

    public function testMaxLengthOptionOverridesAdapterDefault(): void
    {
        $bot = new EitaaAdapter('token', new FakeHttpClient(), ['max_message_length' => 500]);

        self::assertSame(500, $bot->maxMessageLength());
        self::assertLessThanOrEqual(500, mb_strlen($bot->splitMessage(str_repeat('x', 900))[0]));
    }

    public function testSendMessageAutomaticallySendsAllChunks(): void
    {
        $http = new FakeHttpClient([
            FakeHttpClient::ok(['message_id' => 1]),
            FakeHttpClient::ok(['message_id' => 2]),
        ]);
        $bot = new TelegramAdapter('token', $http);
        $text = rtrim(str_repeat('کلمه ', 1000));

        $keyboard = InlineKeyboard::make()->row([InlineKeyboard::callback('بعدی', 'next')]);
        $first = $bot->sendMessage(42, $text, ['keyboard' => $keyboard]);

        self::assertSame(1, $first['result']['message_id']);
        self::assertSame(2, $http->requestCount());
        self::assertArrayHasKey('reply_markup', $http->requests[0]['payload']);
        self::assertArrayNotHasKey('reply_markup', $http->requests[1]['payload']);

        $sent = [];

        foreach ($http->requests as $request) {
            $chunk = $request['payload']['text'];
            self::assertLessThanOrEqual(4096, mb_strlen($chunk, 'UTF-8'));
            $sent[] = $chunk;
        }

        self::assertSame($text, implode(' ', $sent));
    }

    public function testEmptyTextIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new TelegramAdapter('token', new FakeHttpClient()))->splitMessage('');
    }

    public function testZeroLimitIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        (new TelegramAdapter('token', new FakeHttpClient()))->splitMessage('hello', 0);
    }
}
