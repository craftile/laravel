<?php

namespace Craftile\Laravel;

use Craftile\Laravel\Facades\BlockDatastore;

class PreviewDataCollector
{
    private array $regions = [];

    private array $blocks = [];

    private ?string $currentRegion = null;

    private bool $inContentRegion = false;

    private array $sharedRegions = [];

    private array $regionsBeforeContent = [];

    private array $regionsInContent = [];

    private array $regionsAfterContent = [];

    private string $currentLayer = 'beforeContent';

    /**
     * Track the actual render order of children for each parent block.
     * Structure: ['parentId' => ['child1', 'child2', ...]]
     */
    private array $renderedChildren = [];

    /**
     * Start tracking a region.
     */
    public function startRegion(string $regionName): void
    {
        $this->currentRegion = $regionName;

        // Categorize region based on current layer
        if ($this->currentLayer === 'beforeContent') {
            if (! in_array($regionName, $this->regionsBeforeContent)) {
                $this->regionsBeforeContent[] = $regionName;
            }
        } elseif ($this->currentLayer === 'content') {
            if (! in_array($regionName, $this->regionsInContent)) {
                $this->regionsInContent[] = $regionName;
            }
        } else { // afterContent
            if (! in_array($regionName, $this->regionsAfterContent)) {
                $this->regionsAfterContent[] = $regionName;
            }
        }

        // Initialize region if not exists (may already exist from JSON data)
        if (! isset($this->regions[$regionName])) {
            $this->regions[$regionName] = [
                'name' => $regionName,
                'blocks' => [],
            ];
        }

        // Track as shared region if not in content region
        if (! $this->inContentRegion && ! in_array($regionName, $this->sharedRegions)) {
            $this->sharedRegions[] = $regionName;
        }
    }

    /**
     * End tracking a region.
     */
    public function endRegion(string $regionName): void
    {
        if ($this->currentRegion === $regionName) {
            $this->currentRegion = null;
        }
    }

    /**
     * Start tracking a block.
     *
     * Prevents duplicate collection of static blocks in loops
     * Automatically collects ghost children
     */
    public function startBlock(string $blockId, BlockData $blockData): void
    {
        // For static blocks, only store block data if not already collected
        if ($blockData->static && isset($this->blocks[$blockId])) {
            return;
        }

        $this->blocks[$blockId] = $blockData->toArray();

        // Collect all ghost children automatically
        if ($blockData->hasChildren()) {
            foreach ($blockData->childrenIds() as $childId) {
                if (! isset($this->blocks[$childId])) {
                    $childBlock = BlockDatastore::getBlock($childId);
                    if ($childBlock && $childBlock->ghost) {
                        $this->blocks[$childId] = $childBlock->toArray();
                        $this->addToRenderedChildren($blockId, $childId);
                    }
                }
            }
        }

        if ($blockData->parentId) {
            $this->addToRenderedChildren($blockData->parentId, $blockId);
        }

        if ($this->currentRegion) {
            $currentRegion = $this->currentRegion;

            if (isset($this->regions[$currentRegion])) {
                if (! $blockData->parentId && ! in_array($blockId, $this->regions[$currentRegion]['blocks'])) {
                    $this->regions[$currentRegion]['blocks'][] = $blockId;
                }
            }
        }
    }

    /**
     * End tracking a block.
     */
    public function endBlock(string $blockId): void
    {
        //
    }

    /**
     * Add a child block to its parent's rendered children list.
     */
    private function addToRenderedChildren(string $parentId, string $childId): void
    {
        if (! isset($this->renderedChildren[$parentId])) {
            $this->renderedChildren[$parentId] = [];
        }

        if (! in_array($childId, $this->renderedChildren[$parentId])) {
            $this->renderedChildren[$parentId][] = $childId;
        }
    }

    /**
     * Collect all static/repeated children that may not have been rendered.
     * Groups them with other static/repeated blocks, or at the end if none rendered.
     */
    private function collectUnrenderedStaticChildren(): void
    {
        foreach ($this->blocks as $blockId => $blockData) {
            $childrenIds = $blockData['children'] ?? [];

            if (empty($childrenIds)) {
                continue;
            }

            // Find unrendered static/repeated children from BlockDatastore
            $unrenderedStatic = [];

            foreach ($childrenIds as $childId) {
                if (isset($this->blocks[$childId])) {
                    continue;
                }

                $childBlock = BlockDatastore::getBlock($childId);

                if (! $childBlock) {
                    continue;
                }

                // Only collect static or repeated blocks (and their entire tree)
                if ($childBlock->static || $childBlock->repeated) {
                    $this->collectBlockTree($childId);
                    $unrenderedStatic[] = $childId;
                }
            }

            if (empty($unrenderedStatic)) {
                continue;
            }

            // Find insertion point: after last static/repeated block, or at end
            $renderedChildren = $this->renderedChildren[$blockId] ?? [];
            $lastStaticIndex = -1;

            foreach ($renderedChildren as $index => $childId) {
                if (isset($this->blocks[$childId])) {
                    $child = $this->blocks[$childId];
                    if (($child['static'] ?? false) || ($child['repeated'] ?? false)) {
                        $lastStaticIndex = $index;
                    }
                }
            }

            // If no static/repeated blocks were rendered, append at end
            // Otherwise, insert after the last static/repeated block
            $insertionIndex = $lastStaticIndex === -1
                ? count($renderedChildren)
                : $lastStaticIndex + 1;

            // Insert unrendered static blocks at insertion point
            array_splice($renderedChildren, $insertionIndex, 0, $unrenderedStatic);
            $this->renderedChildren[$blockId] = $renderedChildren;
        }
    }

    /**
     * Recursively collect a block and all its children from BlockDatastore.
     */
    private function collectBlockTree(string $blockId): void
    {
        if (isset($this->blocks[$blockId])) {
            return;
        }

        $block = BlockDatastore::getBlock($blockId);

        if (! $block) {
            return;
        }

        $this->blocks[$blockId] = $block->toArray();

        $childrenIds = $block->childrenIds();
        foreach ($childrenIds as $childId) {
            $this->collectBlockTree($childId);
        }
    }

    /**
     * Get all collected data in the format expected by the craftile preview client.
     */
    public function getCollectedData(): array
    {
        $this->collectUnrenderedStaticChildren();

        // Update parent blocks with actual rendered children order
        foreach ($this->renderedChildren as $parentId => $childrenInOrder) {
            if (isset($this->blocks[$parentId])) {
                $this->blocks[$parentId]['children'] = $childrenInOrder;
            }
        }

        $regionsArray = [];

        // Add regions in proper template order:
        // 1. Regions before content (e.g., header)
        // 2. Regions in content (e.g., main)
        // 3. Regions after content (e.g., footer)

        $orderedRegionNames = array_merge(
            $this->regionsBeforeContent,
            $this->regionsInContent,
            $this->regionsAfterContent
        );

        foreach ($orderedRegionNames as $regionName) {
            if (isset($this->regions[$regionName])) {
                $regionData = $this->regions[$regionName];
                $regionData['shared'] = in_array($regionName, $this->sharedRegions);
                $regionsArray[] = $regionData;
            }
        }

        // Add any regions that weren't categorized (shouldn't happen in normal flow)
        foreach ($this->regions as $regionName => $regionData) {
            if (! in_array($regionName, $orderedRegionNames)) {
                $regionData['shared'] = in_array($regionName, $this->sharedRegions);
                $regionsArray[] = $regionData;
            }
        }

        return [
            'blocks' => $this->blocks,
            'regions' => $regionsArray,
            // 'regionsBeforeContent' => $this->regionsBeforeContent,
            // 'regionsInContent' => $this->regionsInContent,
            // 'regionsAfterContent' => $this->regionsAfterContent,
        ];
    }

    /**
     * Reset collector for new request.
     */
    public function reset(): void
    {
        $this->regions = [];
        $this->blocks = [];
        $this->currentRegion = null;
        $this->sharedRegions = [];
        $this->regionsBeforeContent = [];
        $this->regionsInContent = [];
        $this->regionsAfterContent = [];
        $this->currentLayer = 'beforeContent';
        $this->inContentRegion = false;
    }

    /**
     * Start tracking content region (page-specific content).
     */
    public function startContent(): void
    {
        $this->inContentRegion = true;
        $this->currentLayer = 'content';
    }

    /**
     * End tracking content region (page-specific content).
     */
    public function endContent(): void
    {
        $this->inContentRegion = false;
        $this->currentLayer = 'beforeContent';
    }

    /**
     * Mark the start of main content area (should be called by layout).
     */
    public function beforeContent(): void
    {
        $this->currentLayer = 'content';
    }

    /**
     * Mark the end of main content area (called by layout).
     */
    public function afterContent(): void
    {
        $this->currentLayer = 'afterContent';
    }

    /**
     * Check if currently in page-specific content region.
     */
    public function inContentRegion(): bool
    {
        return $this->inContentRegion;
    }

    /**
     * Check if we're currently collecting data (i.e., in preview mode).
     */
    public function isCollecting(): bool
    {
        if (! request()) {
            return false;
        }

        return app('craftile')->inPreview();
    }
}
