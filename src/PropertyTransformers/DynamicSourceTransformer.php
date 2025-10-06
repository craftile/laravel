<?php

declare(strict_types=1);

namespace Craftile\Laravel\PropertyTransformers;

use Craftile\Core\Data\DynamicSource;
use Craftile\Laravel\Contracts\PropertyTransformerInterface;
use Illuminate\Support\Arr;

/**
 * Transforms dynamic source values by resolving them from context.
 */
class DynamicSourceTransformer implements PropertyTransformerInterface
{
    /**
     * Transform a DynamicSource by resolving it from context.
     */
    public function transform(mixed $value): mixed
    {
        if (! $value instanceof DynamicSource) {
            return $value;
        }

        // Resolve the value from context using dot notation
        $resolved = Arr::get($value->context, $value->path, $value->default);

        return $resolved;
    }
}
