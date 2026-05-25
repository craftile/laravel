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
     *
     * @param  array<string, mixed>  $schema
     */
    public function transform(mixed $value, array $schema): mixed
    {
        if (! $value instanceof DynamicSource) {
            return $value;
        }

        $default = $value->schema['default'] ?? null;

        return Arr::get($value->context, $value->path, $default);
    }
}
