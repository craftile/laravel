<?php

declare(strict_types=1);

namespace Craftile\Laravel;

use Craftile\Laravel\Contracts\PropertyTransformerInterface;

/**
 * Registry for property value transformers.
 */
class PropertyTransformerRegistry
{
    /** @var array<string, PropertyTransformerInterface|callable> */
    protected array $transformers = [];

    /**
     * Register a transformer for a property type.
     */
    public function register(string $type, PropertyTransformerInterface|callable $transformer): void
    {
        $this->transformers[$type] = $transformer;
    }

    /**
     * Transform a value using the registered transformer for the given type.
     */
    public function transform(mixed $value, string $type): mixed
    {
        if (! isset($this->transformers[$type])) {
            return $value;
        }

        $transformer = $this->transformers[$type];

        if ($transformer instanceof PropertyTransformerInterface) {
            return $transformer->transform($value);
        }

        if (is_callable($transformer)) {
            return $transformer($value);
        }

        return $value;
    }

    /**
     * Check if a transformer is registered for the given type.
     */
    public function has(string $type): bool
    {
        return isset($this->transformers[$type]);
    }

    /**
     * Get all registered transformer types.
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->transformers);
    }

    /**
     * Remove a transformer for a property type.
     */
    public function remove(string $type): void
    {
        unset($this->transformers[$type]);
    }

    /**
     * Clear all registered transformers.
     */
    public function clear(): void
    {
        $this->transformers = [];
    }
}
