<?php

declare(strict_types=1);

namespace IranMessenger\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * Optional Laravel facade.
 *
 * @method static array sendMessage(int|string $chatId, string $text, array $options = [])
 * @method static array sendPhoto(int|string $chatId, string $photo, array $options = [])
 * @method static array sendDocument(int|string $chatId, string $document, array $options = [])
 * @method static array editMessage(int|string $chatId, int|string $messageId, string $text, array $options = [])
 * @method static array answerCallbackQuery(string $callbackQueryId, array $options = [])
 * @method static array deleteMessage(int|string $chatId, int|string $messageId)
 * @method static array getUpdates(array $options = [])
 * @method static array setWebhook(string $url, array $options = [])
 * @method static array deleteWebhook(array $options = [])
 * @method static array getMe()
 * @method static bool supports(string $feature)
 *
 * @see \IranMessenger\Messenger
 */
final class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iran-messenger';
    }
}
