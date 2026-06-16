<?php

namespace Craftile\Laravel\Exceptions;

use RuntimeException;
use Throwable;

class DiscoveredSchemaRegistrationException extends RuntimeException
{
    public static function forBlock(string $class, ?string $path = null, ?Throwable $previous = null): self
    {
        $message = "Failed to register discovered Craftile block [{$class}]";

        if ($path) {
            $message .= " from [{$path}]";
        }

        return new self($message.'.', previous: $previous);
    }

    public static function forPreset(string $class, ?string $path = null, ?Throwable $previous = null): self
    {
        $message = "Failed to register discovered Craftile preset [{$class}]";

        if ($path) {
            $message .= " from [{$path}]";
        }

        return new self($message.'.', previous: $previous);
    }
}
