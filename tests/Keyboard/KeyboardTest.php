<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Keyboard;

use IranMessenger\Exception\ValidationException;
use IranMessenger\Keyboard\InlineKeyboard;
use IranMessenger\Keyboard\ReplyKeyboard;
use PHPUnit\Framework\TestCase;

final class KeyboardTest extends TestCase
{
    public function testInlineKeyboardBuildsExactArrays(): void
    {
        $keyboard = InlineKeyboard::make()
            ->row([
                InlineKeyboard::callback('بله', 'confirm:yes'),
                InlineKeyboard::url('راهنما', 'https://example.com/help'),
            ])
            ->row([
                InlineKeyboard::callback('خیر', 'confirm:no'),
            ]);

        self::assertSame([
            'inline_keyboard' => [
                [
                    ['text' => 'بله', 'callback_data' => 'confirm:yes'],
                    ['text' => 'راهنما', 'url' => 'https://example.com/help'],
                ],
                [
                    ['text' => 'خیر', 'callback_data' => 'confirm:no'],
                ],
            ],
        ], $keyboard->toArray());
    }

    public function testInlineKeyboardIsImmutable(): void
    {
        $base = InlineKeyboard::make()->row([InlineKeyboard::callback('A', 'a')]);
        $extended = $base->row([InlineKeyboard::callback('B', 'b')]);

        self::assertCount(1, $base->toArray()['inline_keyboard']);
        self::assertCount(2, $extended->toArray()['inline_keyboard']);
        self::assertNotSame($base, $extended);
    }

    public function testInlineButtonWithoutActionIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        InlineKeyboard::make()->row([['text' => 'No action']]);
    }

    public function testInlineButtonWithTwoActionsIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        InlineKeyboard::make()->row([[
            'text' => 'Both',
            'callback_data' => 'x',
            'url' => 'https://example.com',
        ]]);
    }

    public function testCallbackDataOver64BytesIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        InlineKeyboard::callback('Too long', str_repeat('a', 65));
    }

    public function testReplyKeyboardBuildsExactArrayWithFlags(): void
    {
        $keyboard = ReplyKeyboard::make()
            ->row(['📦 سفارش‌ها', '👤 حساب من'])
            ->row([['text' => '☎️ ارسال شماره', 'request_contact' => true]])
            ->resize()
            ->oneTime()
            ->selective()
            ->placeholder('یک گزینه انتخاب کنید');

        self::assertSame([
            'keyboard' => [
                [
                    ['text' => '📦 سفارش‌ها'],
                    ['text' => '👤 حساب من'],
                ],
                [
                    ['text' => '☎️ ارسال شماره', 'request_contact' => true],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'selective' => true,
            'input_field_placeholder' => 'یک گزینه انتخاب کنید',
        ], $keyboard->toArray());
    }

    public function testReplyKeyboardDefaultsHaveNoFlags(): void
    {
        $keyboard = ReplyKeyboard::make()->row(['Yes', 'No']);

        self::assertSame([
            'keyboard' => [
                [['text' => 'Yes'], ['text' => 'No']],
            ],
        ], $keyboard->toArray());
    }

    public function testReplyKeyboardFlagsCanBeDisabledAfterEnabling(): void
    {
        $keyboard = ReplyKeyboard::make()
            ->row(['Yes'])
            ->resize()
            ->oneTime()
            ->selective()
            ->resize(false)
            ->oneTime(false)
            ->selective(false);

        self::assertSame(['keyboard' => [[['text' => 'Yes']]]], $keyboard->toArray());
    }

    public function testEmptyReplyRowIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        ReplyKeyboard::make()->row([]);
    }
}
