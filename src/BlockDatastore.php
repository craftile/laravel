<?php

namespace Craftile\Laravel;

use Craftile\Laravel\Facades\Craftile;
use Craftile\Laravel\View\JsonViewParser;

class BlockDatastore
{
    /**
     * In-memory cache of loaded BlockData instances
     * Structure: ['blockId' => BlockData].
     */
    private static array $loadedBlocks = [];

    public function __construct(
        protected JsonViewParser $parser
    ) {}

    /**
     * Load all blocks from a file into memory cache.
     */
    public function loadFile(string $sourceFilePath): void
    {
        if (! file_exists($sourceFilePath)) {
            return;
        }

        $blocks = $this->getBlocksArray($sourceFilePath);

        // Create BlockData for each block and store in cache
        foreach ($blocks as $blockId => $blockData) {
            $block = Craftile::createBlockData(
                $blockData,
                fn ($childId) => self::$loadedBlocks[$childId] ?? null
            );

            $block->setSourceFile($sourceFilePath);
            self::$loadedBlocks[$blockId] = $block;
        }
    }

    /**
     * Get a specific block by ID (must be loaded first).
     *
     * @param  string  $blockId  The block ID to retrieve
     * @param  array  $overrides  Context values from template that override stored block data
     */
    public function getBlock(string $blockId, array $overrides = []): ?BlockData
    {
        $existingBlock = self::$loadedBlocks[$blockId] ?? null;

        if (! $existingBlock) {
            return null;
        }

        if (empty($overrides)) {
            return $existingBlock;
        }

        $blockArray = $existingBlock->toArray();

        $mergedData = $blockArray;
        if (isset($overrides['properties']) && isset($blockArray['properties'])) {
            $mergedData['properties'] = array_merge($blockArray['properties'], $overrides['properties']);
        }

        foreach ($overrides as $key => $value) {
            if ($key !== 'properties') {
                $mergedData[$key] = $value;
            }
        }

        $block = Craftile::createBlockData(
            $mergedData,
            fn ($childId) => self::$loadedBlocks[$childId] ?? null
        );

        $block->setSourceFile($existingBlock->getSourceFile());

        return $block;
    }

    /**
     * Check if a block exists in memory cache.
     */
    public function hasBlock(string $blockId): bool
    {
        return isset(self::$loadedBlocks[$blockId]);
    }

    /**
     * Get all loaded blocks.
     */
    public function getAllBlocks(): array
    {
        return self::$loadedBlocks;
    }

    /**
     * Get all blocks array from the given file.
     */
    public function getBlocksArray(string $sourceFilePath): array
    {
        try {
            $template = $this->parser->parse($sourceFilePath);
        } catch (\Exception $e) {
            return [];
        }

        if (! is_array($template)) {
            return [];
        }

        $blocks = $template['blocks'] ?? [];

        $this->assignBlockIndices($blocks, $template);

        return $blocks;
    }

    /**
     * Assign indices to blocks based on their position.
     */
    protected function assignBlockIndices(array &$blocks, array $template): void
    {
        // Assign indices for blocks in regions
        foreach ($template['regions'] ?? [] as $region) {
            foreach ($region['blocks'] ?? [] as $index => $blockId) {
                if (isset($blocks[$blockId])) {
                    $blocks[$blockId]['index'] = $index;
                }
            }
        }

        // Assign indices for child blocks
        foreach ($blocks as $blockId => $blockData) {
            foreach ($blockData['children'] ?? [] as $index => $childId) {
                if (isset($blocks[$childId])) {
                    $blocks[$childId]['index'] = $index;
                }
            }
        }
    }

    /**
     * Clear all loaded blocks and parsed files (useful for testing).
     */
    public function clear(): void
    {
        self::$loadedBlocks = [];
        $this->parser->clearCache();
    }
}
