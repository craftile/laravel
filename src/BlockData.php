<?php

namespace Craftile\Laravel;

use Craftile\Core\Data\BlockData as CoreBlockData;

class BlockData extends CoreBlockData
{
    /**
     * Get editor attributes
     */
    public function craftileAttributes(): EditorAttributes
    {
        return new EditorAttributes($this, \craftile()->inPreview());
    }

    /**
     * Get resolved children as BlockData instances.
     */
    public function children(): array
    {
        if (! $this->resolveChildData) {
            return [];
        }

        return array_map(fn ($id) => ($this->resolveChildData)($id), $this->childrenIds());
    }

    /**
     * Get raw children block IDs.
     */
    public function childrenIds(): array
    {
        return $this->childrenIds;
    }

    public static function make(array $blockData, $resolveChildData = null): static
    {
        return new static(
            id: $blockData['id'] ?? '',
            type: $blockData['type'] ?? '',
            properties: self::createPropertyBag($blockData['properties'] ?? [], $blockData['type'] ?? ''),
            parentId: $blockData['parentId'] ?? null,
            childrenIds: $blockData['children'] ?? [],
            disabled: $blockData['disabled'] ?? false,
            static: $blockData['static'] ?? false,
            repeated: $blockData['repeated'] ?? false,
            semanticId: $blockData['semanticId'] ?? null,
            index: $blockData['index'] ?? null,
            resolveChildData: $resolveChildData,
        );
    }

    protected static function createPropertyBag(array $properties, string $blockType): PropertyBag
    {
        $schemas = [];

        if ($blockType) {
            try {
                $blockSchema = \craftile()->getBlockSchema($blockType);
                if ($blockSchema) {
                    $propertiesDefinitions = $blockSchema->properties;
                    // Convert Property objects to arrays and key by id
                    $schemas = collect($propertiesDefinitions)
                        ->map(fn ($prop) => is_object($prop) ? $prop->toArray() : $prop)
                        ->filter(fn ($schema) => array_key_exists('id', $schema))
                        ->keyBy('id')
                        ->toArray();
                }
            } catch (\Exception) {
                // Continue silently if schema lookup fails
            }
        }

        // Prepare properties with defaults from schemas
        $preparedProperties = collect($schemas)
            ->mapWithKeys(fn ($schema) => [
                $schema['id'] => array_key_exists($schema['id'], $properties)
                    ? $properties[$schema['id']]
                    : ($schema['default'] ?? null),
            ])
            ->merge($properties) // Include any properties not in schema
            ->all();

        return new PropertyBag($preparedProperties, $schemas);
    }
}
