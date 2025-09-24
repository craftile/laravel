<?php

namespace Craftile\Laravel;

use Craftile\Core\Services\BlockFlattener as CoreBlockFlattener;

class BlockFlattener extends CoreBlockFlattener
{
    /**
     * Generate a unique ID using a centralized method.
     * Ensures consistent hash algorithm across the entire system.
     */
    protected function generateUniqueId(?string $parentId, string $childLocalId): string
    {
        if (empty($parentId)) {
            return $childLocalId;
        }

        $uniqueId = craftile()->generateChildId($parentId, $childLocalId);

        $this->idMappings["{$parentId}.{$childLocalId}"] = $uniqueId;

        return $uniqueId;
    }
}
