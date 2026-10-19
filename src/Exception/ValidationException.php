<?php

declare(strict_types=1);

namespace IranMessenger\Exception;

use InvalidArgumentException;

/**
 * Thrown when a caller supplies invalid data before a request is sent.
 */
final class ValidationException extends InvalidArgumentException
{
}
