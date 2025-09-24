<?php

namespace Craftile\Laravel;

use Craftile\Core\Data\PropertyBag as CorePropertyBag;

class PropertyBag extends CorePropertyBag
{
    /**
     * Transform a value based on its type using Laravel's transformer registry.
     */
    protected function transformValue(mixed $value, ?string $type): mixed
    {
        if (! $type) {
            return $value;
        }

        try {
            $transformerRegistry = app(PropertyTransformerRegistry::class);

            return $transformerRegistry->transform($value, $type);
        } catch (\Exception) {
            // Fall back to parent transformation if registry fails
        }

        return parent::transformValue($value, $type);
    }
}
