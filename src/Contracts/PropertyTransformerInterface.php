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
     *
     * @param  array<string, mixed>  $schema
     */
    public function transform(mixed $value, array $schema): mixed;
}
