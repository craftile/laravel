<?php

namespace Craftile\Laravel;

use Craftile\Core\Data\BlockSchema;
use Craftile\Core\Services\BlockSchemaRegistry as CoreBlockSchemaRegistry;
use Craftile\Laravel\Events\BlockSchemaRegistered;
use Illuminate\Support\Collection;

class BlockSchemaRegistry extends CoreBlockSchemaRegistry
{
    public function register(BlockSchema $schema): void
    {
        parent::register($schema);

        event(new BlockSchemaRegistered($schema));
    }

    /**
     * Get schemas grouped by category.
     */
    public function getByCategory(): Collection
    {
        $grouped = [];

        foreach ($this->getAllSchemas() as $schema) {
            $category = $schema->category ?? 'default';

            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }

            $grouped[$category][] = $schema;
        }

        return collect($grouped)->map(fn ($schemas) => collect($schemas));
    }
}
