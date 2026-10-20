<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Dto;

use IranMessenger\Dto\Update;
use IranMessenger\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Webhook payloads are attacker-controlled. Array/object values in scalar
 * positions must not raise "Array to string conversion" (which Laravel turns
 * into an ErrorException) or a TypeError.
 */
final class MalformedPayloadTest extends TestCase
{
    public function testArrayValuesInMessageScalarsAreIgnored(): void
    {
        $update = Update::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'date' => 1760000000,
                'chat' => ['id' => 5, 'type' => ['nested'], 'title' => ['nested']],
                'from' => ['id' => 9, 'is_bot' => false, 'first_name' => ['nested']],
                'text' => ['nested'],
                'caption' => ['nested'],
            ],
        ]);

        $message = $update->message;

        self::assertNotNull($message);
        self::assertNull($message->text);
        self::assertNull($message->caption);
        self::assertSame('unknown', $message->chat->type);
        self::assertNull($message->chat->title);
        self::assertSame('', $message->from?->firstName);
    }

    public function testNumericStringsAreAcceptedForIdsAndDates(): void
    {
        $update = Update::fromArray([
            'update_id' => '77',
            'message' => [
                'message_id' => '10',
                'date' => '1760000000',
                'chat' => ['id' => '-100123', 'type' => 'supergroup'],
                'text' => 'hello',
            ],
        ]);

        self::assertSame(77, $update->updateId);
        self::assertSame(10, $update->message?->messageId);
        self::assertSame(1760000000, $update->message?->date);
        self::assertSame(-100123, $update->message?->chat->id);
    }

    public function testMissingUpdateIdIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Update::fromArray(['message' => []]);
    }

    public function testNonNumericUpdateIdIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Update::fromArray(['update_id' => 'abc']);
    }

    public function testCallbackQueryWithArrayDataDoesNotCrash(): void
    {
        $update = Update::fromArray([
            'update_id' => 3,
            'callback_query' => [
                'id' => 'cb-1',
                'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Ali'],
                'data' => ['nested'],
                'chat_instance' => ['nested'],
            ],
        ]);

        self::assertTrue($update->isCallbackQuery());
        self::assertNull($update->callbackQuery?->data);
        self::assertNull($update->callbackQuery?->chatInstance);
        self::assertSame('Ali', $update->callbackQuery?->from->firstName);
    }

    public function testCallbackQueryWithoutFromIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Update::fromArray([
            'update_id' => 4,
            'callback_query' => ['id' => 'cb-2'],
        ]);
    }
}
