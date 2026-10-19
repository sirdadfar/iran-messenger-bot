<?php

declare(strict_types=1);

namespace IranMessenger\Tests;

use IranMessenger\Adapter\BaleAdapter;
use IranMessenger\Adapter\EitaaAdapter;
use IranMessenger\Adapter\RubikaAdapter;
use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Messenger;
use IranMessenger\MessengerFactory;
use IranMessenger\Tests\Fake\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class MessengerFactoryTest extends TestCase
{
    public function testFactoryReturnsTheCorrectAdapterForEveryDriver(): void
    {
        $drivers = [
            'telegram' => TelegramAdapter::class,
            'bale' => BaleAdapter::class,
            'eitaa' => EitaaAdapter::class,
            'rubika' => RubikaAdapter::class,
        ];

        foreach ($drivers as $driver => $class) {
            $bot = MessengerFactory::make($driver, 'test-token');

            self::assertInstanceOf($class, $bot);
            self::assertInstanceOf(Messenger::class, $bot);
            self::assertSame($driver, $bot->driver());
            self::assertTrue($bot->supports('send_message'));
        }
    }

    public function testDriverNameIsNormalised(): void
    {
        $bot = MessengerFactory::make('  TeLeGrAm  ', 'test-token');

        self::assertInstanceOf(TelegramAdapter::class, $bot);
    }

    public function testOptionsArePassedToTheAdapter(): void
    {
        $http = new FakeHttpClient([FakeHttpClient::ok(true)]);
        $bot = MessengerFactory::make('eitaa', 'test-token', [
            'http_client' => $http,
            'max_message_length' => 250,
            'base_url' => 'https://proxy.test/api',
        ]);

        self::assertSame(250, $bot->maxMessageLength());

        $bot->sendMessage(1, 'hi');

        self::assertSame('https://proxy.test/api/test-token/sendMessage', $http->lastRequest()['url']);
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unknown messenger driver "whatsapp"');

        MessengerFactory::make('whatsapp', 'test-token');
    }

    public function testEmptyTokenThrows(): void
    {
        $this->expectException(ValidationException::class);

        MessengerFactory::make('telegram', '   ');
    }

    public function testInvalidHttpClientOptionThrows(): void
    {
        $this->expectException(ValidationException::class);

        MessengerFactory::make('telegram', 'test-token', ['http_client' => new \stdClass()]);
    }

    public function testFeatureConstantsAreExposed(): void
    {
        self::assertSame('inline_keyboard', Messenger::FEATURE_INLINE_KEYBOARD);
        self::assertSame('send_message', Messenger::FEATURE_SEND_MESSAGE);
        self::assertSame('polling', Messenger::FEATURE_POLLING);
    }
}
