<?php

declare(strict_types=1);

namespace IranMessenger\Exception;

use RuntimeException;

/**
 * Thrown when a provider does not implement a capability, for example inline
 * keyboards on Eitaa.
 */
final class UnsupportedFeatureException extends RuntimeException
{
    public function __construct(
        private readonly string $feature,
        private readonly string $driver,
        string $message = '',
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : sprintf('Feature "%s" is not supported by the %s adapter.', $feature, $driver),
        );
    }

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }
}
