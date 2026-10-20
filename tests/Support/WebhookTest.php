<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Support;

use IranMessenger\Exception\ValidationException;
use IranMessenger\Support\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'aZ09_-secret_value_for_tests';

    public function testVerifyUsesExactMatch(): void
    {
        self::assertTrue(Webhook::verify(self::SECRET, self::SECRET));
        self::assertFalse(Webhook::verify(self::SECRET . 'x', self::SECRET));
        self::assertFalse(Webhook::verify(strtoupper(self::SECRET), self::SECRET));
        self::assertFalse(Webhook::verify(null, self::SECRET));
        self::assertFalse(Webhook::verify('', self::SECRET));
        self::assertFalse(Webhook::verify(self::SECRET, ''));
    }

    public function testIsValidSecretMatchesProviderRules(): void
    {
        self::assertTrue(Webhook::isValidSecret(self::SECRET));
        self::assertTrue(Webhook::isValidSecret(str_repeat('a', Webhook::MAX_SECRET_LENGTH)));
        self::assertFalse(Webhook::isValidSecret(''));
        self::assertFalse(Webhook::isValidSecret(str_repeat('a', Webhook::MAX_SECRET_LENGTH + 1)));
        self::assertFalse(Webhook::isValidSecret('has space'));
        self::assertFalse(Webhook::isValidSecret('semi;colon'));
        self::assertFalse(Webhook::isValidSecret("new\nline"));
    }

    public function testVerifyServerHeaderFindsTheStandardAndLowercaseKeys(): void
    {
        self::assertTrue(Webhook::verifyServerHeader(
            ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => self::SECRET],
            self::SECRET,
        ));

        self::assertTrue(Webhook::verifyServerHeader(
            ['x-telegram-bot-api-secret-token' => self::SECRET],
            self::SECRET,
        ));

        self::assertFalse(Webhook::verifyServerHeader([], self::SECRET));
        self::assertFalse(Webhook::verifyServerHeader(
            ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong'],
            self::SECRET,
        ));
    }

    public function testVerifyServerHeaderSupportsACustomHeaderName(): void
    {
        self::assertTrue(Webhook::verifyServerHeader(
            ['HTTP_X_BAZAAR_SECRET' => 'token'],
            'token',
            'X-Bazaar-Secret',
        ));
    }

    public function testDecodeReturnsAnArrayAndRejectsEmptyOrScalarBodies(): void
    {
        self::assertSame(['update_id' => 1], Webhook::decode('{"update_id":1}'));

        $this->expectException(ValidationException::class);
        Webhook::decode('');
    }

    public function testDecodeRejectsScalarJson(): void
    {
        $this->expectException(ValidationException::class);
        Webhook::decode('"just a string"');
    }

    public function testDecodeRejectsInvalidJson(): void
    {
        $this->expectException(ValidationException::class);
        Webhook::decode('{not json');
    }

    public function testDecodeEnforcesTheBodyLimit(): void
    {
        $body = '{"update_id":1}';

        self::assertSame(1, Webhook::decode($body, strlen($body))['update_id']);

        $this->expectException(ValidationException::class);
        Webhook::decode($body, strlen($body) - 1);
    }

    public function testDecodeRejectsANonPositiveLimit(): void
    {
        $this->expectException(ValidationException::class);
        Webhook::decode('{"update_id":1}', 0);
    }

    public function testUpdateParsesADecodedBody(): void
    {
        $update = Webhook::update('{"update_id":42,"message":{"message_id":7,"date":1760000000,"chat":{"id":99,"type":"private"},"text":"سلام"}}');

        self::assertSame(42, $update->updateId);
        self::assertSame('message', $update->type());
        self::assertSame('سلام', $update->message?->text);
        self::assertSame(99, $update->message?->chat->id);
    }

    public function testGenerateSecretIsUrlSafeAndLongEnough(): void
    {
        $secret = Webhook::generateSecret();

        self::assertTrue(Webhook::isValidSecret($secret));
        self::assertGreaterThanOrEqual(43, strlen($secret));
        self::assertNotSame($secret, Webhook::generateSecret());
    }

    public function testGenerateSecretRequiresEnoughEntropy(): void
    {
        $this->expectException(ValidationException::class);
        Webhook::generateSecret(8);
    }
}
