<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Dto;

use IranMessenger\Dto\Message;
use IranMessenger\Dto\Update;
use IranMessenger\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class UpdateTest extends TestCase
{
    public function testParsesTextMessageUpdate(): void
    {
        $update = Update::fromArray($this->fixture('telegram-text.json'));

        self::assertSame(400001, $update->updateId);
        self::assertSame('message', $update->type());
        self::assertInstanceOf(Message::class, $update->message);
        self::assertNull($update->editedMessage);
        self::assertFalse($update->isCallbackQuery());

        $message = $update->message;
        self::assertSame(101, $message->messageId);
        self::assertSame('سلام، این یک پیام تست است.', $message->text);
        self::assertSame(1760000000, $message->date);
        self::assertSame(123456789, $message->chat->id);
        self::assertSame('private', $message->chat->type);
        self::assertSame('ali_r', $message->from?->username);
        self::assertSame('علی رضایی', $message->from?->fullName());
        self::assertTrue($message->from?->isPremium);
        self::assertFalse($message->isCommand());
        self::assertFalse($message->hasPhoto());
    }

    public function testParsesPhotoMessageAndSizes(): void
    {
        $update = Update::fromArray($this->fixture('telegram-photo.json'));

        self::assertSame(400002, $update->updateId);
        self::assertSame('message', $update->type());
        self::assertNotNull($update->message);

        $message = $update->message;
        self::assertTrue($message->hasPhoto());
        self::assertCount(2, $message->photo);
        self::assertSame('AgACAgQAAxkBAAI-small', $message->photo[0]->fileId);
        self::assertSame('AQAD-small', $message->photo[0]->fileUniqueId);
        self::assertSame(90, $message->photo[0]->width);
        self::assertSame(1234, $message->photo[0]->fileSize);
        self::assertSame(800, $message->photo[1]->width);
        self::assertSame(800, $message->photo[1]->height);
        self::assertSame(98765, $message->photo[1]->fileSize);
        self::assertSame('اسکرین‌شات پنل', $message->caption);
        self::assertSame(-1001234567890, $message->chat->id);
        self::assertSame('supergroup', $message->chat->type);
        self::assertSame('تیم پشتیبانی', $message->chat->displayName());
        self::assertNull($message->document);
    }

    public function testParsesCallbackQueryUpdate(): void
    {
        $update = Update::fromArray($this->fixture('telegram-callback.json'));

        self::assertTrue($update->isCallbackQuery());
        self::assertSame('callback_query', $update->type());
        self::assertNull($update->message);

        $callback = $update->callbackQuery;
        self::assertNotNull($callback);
        self::assertSame('4382900011223344556', $callback->id);
        self::assertSame('confirm:42', $callback->data);
        self::assertSame(123456789, $callback->from->id);
        self::assertSame('علی رضایی', $callback->from->fullName());
        self::assertSame('-1234567890123456789', $callback->chatInstance);
        self::assertSame(101, $callback->message?->messageId);
        self::assertSame('سفارش خود را تأیید کنید:', $callback->message?->text);
    }

    public function testParsesEditedMessageUpdate(): void
    {
        $update = Update::fromArray($this->fixture('telegram-edited.json'));

        self::assertTrue($update->isEditedMessage());
        self::assertSame('edited_message', $update->type());
        self::assertNull($update->message);
        self::assertSame('سلام، پیام ویرایش شد.', $update->editedMessage?->text);
        self::assertSame(1760000300, $update->editedMessage?->raw['edit_date']);
        self::assertSame(101, $update->editedMessage?->messageId);
    }

    public function testUpdateWithoutIdIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        Update::fromArray(['message' => []]);
    }

    public function testMessageWithoutChatIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        Message::fromArray(['message_id' => 5, 'date' => 1760000000]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $path = __DIR__ . '/../Fixtures/' . $name;
        $contents = file_get_contents($path);
        self::assertIsString($contents, 'Fixture must be readable: ' . $path);

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
