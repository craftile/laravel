<?php

namespace Craftile\Laravel\Support;

use Craftile\Laravel\BlockFlattener;
use Craftile\Laravel\Data\UpdateRequest;
use Craftile\Laravel\Facades\Craftile;

class HandleUpdates
{
    public function __construct(
        private BlockFlattener $flattener
    ) {}

    /**
     * Apply updates to source data, optionally filtering by target regions
     *
     * @param  array  $sourceData  Original template data
     * @param  UpdateRequest  $updateRequest  Update request with new block states
     * @param  array|null  $targetRegions  Optional array of region names to limit updates to
     * @return array{data: array, updated: bool} Updated template data and whether changes were applied
     */
    public function execute(array $sourceData, UpdateRequest $updateRequest, ?array $targetRegions = null): array
    {
        $data = $this->normalizeSourceData($sourceData);

        if (! $this->hasUpdates($sourceData, $updateRequest, $targetRegions)) {
            return ['data' => $sourceData, 'updated' => false];
        }

        if ($targetRegions === null) {
            return $this->applyUpdates($data, $updateRequest);
        }

        $regionBlocks = $this->getAllRegionBlocks($data, $targetRegions, $updateRequest);
        $filteredChanges = $this->filterChangesForRegions($updateRequest, $regionBlocks, $data, $targetRegions);

        return $this->applyUpdates($data, $updateRequest, $filteredChanges, $regionBlocks, $targetRegions);
    }

    /**
     * Normalize source data to flat structure and ensure required keys
     */
    private function normalizeSourceData(array $sourceData): array
    {
        // Apply custom normalizer if registered
        $sourceData = Craftile::normalizeTemplate($sourceData);

        if ($this->flattener->hasNestedStructure($sourceData)) {
            $sourceData = $this->flattener->flattenNestedStructure($sourceData);

            return [
                'blocks' => $sourceData['blocks'] ?? [],
                'regions' => $sourceData['regions'] ?? [],
            ];
        }

        $regionName = $sourceData['name'] ?? 'main';
        $blocks = $sourceData['blocks'] ?? [];
        $blocksOrder = $sourceData['order'] ?? array_keys($blocks);

        return [
            'blocks' => $blocks,
            'regions' => [
                [
                    'name' => $regionName,
                    'blocks' => $blocksOrder,
                ],
            ],
        ];
    }

    private function applyUpdates(array $data, UpdateRequest $updateRequest, ?array $filteredChanges = null, ?array $regionBlocks = null, ?array $targetRegions = null): array
    {
        // Remove blocks
        $blocksToRemove = $filteredChanges['removed'] ?? $updateRequest->getRemovedBlocks();
        foreach ($blocksToRemove as $blockId) {
            if (isset($data['blocks'][$blockId])) {
                unset($data['blocks'][$blockId]);
            }
        }

        // Update blocks
        foreach ($updateRequest->getBlocks() as $blockId => $blockData) {
            if (in_array($blockId, $blocksToRemove)) {
                continue;
            }

            $shouldUpdate = ! $filteredChanges ||
                in_array($blockId, $regionBlocks) ||
                in_array($blockId, $filteredChanges['added']);

            if ($shouldUpdate) {
                $data['blocks'][$blockId] = $blockData;
            }
        }

        // Update regions
        if (! empty($updateRequest->regions)) {
            $data['regions'] = $targetRegions
                ? array_values(array_filter($updateRequest->regions, fn ($region) => in_array($region['name'], $targetRegions)))
                : $updateRequest->regions;
        }

        return ['data' => $data, 'updated' => true];
    }

    private function hasUpdates(array $data, UpdateRequest $updateRequest, ?array $targetRegions = null): bool
    {
        if ($targetRegions === null) {
            return ! empty($updateRequest->getRemovedBlocks()) ||
                ! empty($updateRequest->getBlocks());
        }

        // Check removed blocks against source data regions
        $sourceRootBlocks = $this->getRootBlocksForRegions($data['regions'] ?? [], $targetRegions);
        foreach ($updateRequest->getRemovedBlocks() as $blockId) {
            if (
                isset($data['blocks'][$blockId]) &&
                ($this->blockOrAncestorInRegion($blockId, $sourceRootBlocks, $data['blocks']) || in_array($blockId, $sourceRootBlocks))
            ) {
                return true;
            }
        }

        // Check added/updated blocks and regions against update request
        $updateRootBlocks = $this->getRootBlocksForRegions($updateRequest->regions, $targetRegions);
        $changedBlocks = array_merge(
            $updateRequest->getAddedBlocks(),
            $updateRequest->getUpdatedBlocks(),
            array_keys($updateRequest->getMovedBlocks())
        );

        foreach ($changedBlocks as $blockId) {
            if (
                $this->blockOrAncestorInRegion($blockId, $updateRootBlocks, array_merge($data['blocks'] ?? [], $updateRequest->getBlocks())) ||
                in_array($blockId, $updateRootBlocks)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get blocks from update request that belong to target regions.
     */
    private function getAllRegionBlocks(array $data, array $targetRegions, UpdateRequest $updateRequest): array
    {
        $rootBlocks = $this->getRootBlocksForRegions($updateRequest->regions, $targetRegions);
        $allBlocks = array_merge($data['blocks'] ?? [], $updateRequest->getBlocks());

        $regionBlocks = $rootBlocks;

        // Only check blocks that are actually in the update request
        foreach (array_keys($updateRequest->getBlocks()) as $blockId) {
            if ($this->blockOrAncestorInRegion($blockId, $rootBlocks, $allBlocks)) {
                $regionBlocks[] = $blockId;
            }
        }

        return array_unique($regionBlocks);
    }

    /**
     * Get the root blocks for the given target regions.
     */
    private function getRootBlocksForRegions(array $regions, array $targetRegions): array
    {
        $rootBlocks = [];

        foreach ($regions as $region) {
            if (in_array($region['name'], $targetRegions)) {
                $rootBlocks = array_merge($rootBlocks, $region['blocks'] ?? []);
            }
        }

        return array_unique($rootBlocks);
    }

    /**
     * Check if block or its ancestors belong to region.
     */
    private function blockOrAncestorInRegion(string $blockId, array $regionRootBlocks, array $blocks): bool
    {
        $currentId = $blockId;

        do {
            if (in_array($currentId, $regionRootBlocks)) {
                return true;
            }

            $currentId = $blocks[$currentId]['parentId'] ?? null;
        } while ($currentId);

        return false;
    }

    /**
     * Filter changes to only include blocks in target regions.
     */
    private function filterChangesForRegions(UpdateRequest $updateRequest, array $regionBlocks, array $sourceData, array $targetRegions): array
    {
        $sourceRootBlocks = $this->getRootBlocksForRegions($sourceData['regions'] ?? [], $targetRegions);
        $sourceBlocks = $sourceData['blocks'] ?? [];

        $inRegion = fn ($blockId) => in_array($blockId, $regionBlocks) ||
            $this->blockOrAncestorInRegion($blockId, $sourceRootBlocks, $sourceBlocks);

        return [
            'added' => array_filter($updateRequest->getAddedBlocks(), fn ($id) => in_array($id, $regionBlocks)),
            'updated' => array_filter($updateRequest->getUpdatedBlocks(), $inRegion),
            'removed' => array_filter($updateRequest->getRemovedBlocks(), $inRegion),
            'moved' => array_filter($updateRequest->getMovedBlocks(), $inRegion, ARRAY_FILTER_USE_KEY),
        ];
    }
}
