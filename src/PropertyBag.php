<?php

namespace Craftile\Laravel;

use Craftile\Core\Data\PropertyBag as CorePropertyBag;

class PropertyBag extends CorePropertyBag
{
    /**
     * Transform a value using Laravel's transformer registry.
     *
     * @param  array<string, mixed>  $schema
     */
    protected function transformValue(mixed $value, array $schema): mixed
    {
        if (! isset($schema['type'])) {
            return $value;
        }

        try {
            $transformerRegistry = app(PropertyTransformerRegistry::class);

            return $transformerRegistry->transform($value, $schema);
        } catch (\Exception) {
            // Fall back to parent transformation if registry fails
        }

        return parent::transformValue($value, $schema);
    }
}
