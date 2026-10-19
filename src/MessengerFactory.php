<?php

declare(strict_types=1);

namespace IranMessenger;

use IranMessenger\Adapter\AbstractAdapter;
use IranMessenger\Adapter\BaleAdapter;
use IranMessenger\Adapter\EitaaAdapter;
use IranMessenger\Adapter\RubikaAdapter;
use IranMessenger\Adapter\TelegramAdapter;
use IranMessenger\Exception\ValidationException;
use IranMessenger\Http\HttpClient;

/**
 * Build an adapter from a short driver name.
 */
final class MessengerFactory
{
    /** @var list<string> */
    public const DRIVERS = ['telegram', 'bale', 'eitaa', 'rubika'];

    /**
     * @param array<string, mixed> $options `base_url`, `timeout`, `max_message_length`,
     *                                      `methods`, `http_client`, ...
     * @return Messenger&AbstractAdapter
     */
    public static function make(string $driver, string $token, array $options = []): Messenger
    {
        $driver = strtolower(trim($driver));
        $http = $options['http_client'] ?? null;
        unset($options['http_client']);

        if ($http !== null && !$http instanceof HttpClient) {
            throw new ValidationException('The "http_client" option must implement IranMessenger\\Http\\HttpClient.');
        }

        return match ($driver) {
            'telegram' => new TelegramAdapter($token, $http, $options),
            'bale' => new BaleAdapter($token, $http, $options),
            'eitaa' => new EitaaAdapter($token, $http, $options),
            'rubika' => new RubikaAdapter($token, $http, $options),
            default => throw new ValidationException(sprintf(
                'Unknown messenger driver "%s". Supported drivers: %s.',
                $driver,
                implode(', ', self::DRIVERS),
            )),
        };
    }
}
