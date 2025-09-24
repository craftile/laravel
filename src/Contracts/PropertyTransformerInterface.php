<?php

declare(strict_types=1);

namespace Craftile\Laravel\Contracts;

/**
 * Interface for property value transformers.
 */
interface PropertyTransformerInterface
{
    /**
     * Transform the given value.
     */
    public function transform(mixed $value): mixed;
}
