<?php

declare(strict_types=1);

namespace IranMessenger\Exception;

use RuntimeException;

/**
 * Thrown for transport-level failures such as a cURL error or timeout.
 *
 * The URL is intentionally not part of the message because provider URLs
 * contain the bot token.
 */
final class NetworkException extends RuntimeException
{
}
