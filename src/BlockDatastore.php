<?php

namespace Craftile\Laravel;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

class BlockDatastore
{
    /**
     * In-memory cache of loaded BlockData instances
     * Structure: ['blockId' => BlockData].
     */
    private static array $loadedBlocks = [];

    public function __construct(
        protected BlockFlattener $flattener
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
            self::$loadedBlocks[$blockId] = BlockData::make(
                $blockData,
                fn ($childId) => self::$loadedBlocks[$childId] ?? null
            );
        }
    }

    /**
     * Get a specific block by ID (must be loaded first).
     *
     * @param  string  $blockId  The block ID to retrieve
     * @param  array  $defaults  Default values to merge with block data (block data takes precedence)
     */
    public function getBlock(string $blockId, array $defaults = []): ?BlockData
    {
        $existingBlock = self::$loadedBlocks[$blockId] ?? null;

        if (! $existingBlock && empty($defaults)) {
            return null;
        }

        if (! $existingBlock) {
            return null;
        }

        if (empty($defaults)) {
            // No defaults to merge, return existing block
            return $existingBlock;
        }

        // Merge defaults with existing block data (existing data takes precedence)
        $blockArray = $existingBlock->toArray();

        $mergedData = $blockArray;
        if (isset($defaults['properties']) && isset($blockArray['properties'])) {
            $mergedData['properties'] = array_merge($defaults['properties'], $blockArray['properties']);
        }

        foreach ($defaults as $key => $value) {
            if ($key !== 'properties' && ! isset($blockArray[$key])) {
                $mergedData[$key] = $value;
            }
        }

        return BlockData::make(
            $mergedData,
            fn ($childId) => self::$loadedBlocks[$childId] ?? null
        );
    }

    /**
     * Check if a block exists in memory cache.
     */
    public function hasBlock(string $blockId): bool
    {
        return isset(self::$loadedBlocks[$blockId]);
    }

    /**
     * Get all blocks array from the given JSON file (with caching).
     */
    public function getBlocksArray(string $sourceFilePath): array
    {
        $cacheKey = 'craftile_blocks_'.md5($sourceFilePath.'_'.filemtime($sourceFilePath));

        return Cache::remember($cacheKey, 300, function () use ($sourceFilePath) {
            return $this->parseBlocksFromFile($sourceFilePath);
        });
    }

    /**
     * Parse blocks array from JSON or YAML file.
     */
    protected function parseBlocksFromFile(string $sourceFilePath): array
    {
        if (! file_exists($sourceFilePath)) {
            return [];
        }

        $content = file_get_contents($sourceFilePath);
        $extension = strtolower(pathinfo($sourceFilePath, PATHINFO_EXTENSION));

        if ($extension === 'yaml' || $extension === 'yml') {
            try {
                $data = Yaml::parse($content);
            } catch (\Exception) {
                return [];
            }
        } else {
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        }

        if (! is_array($data)) {
            return [];
        }

        // Check if the data contains nested block structures and flatten if needed
        if ($this->flattener->hasNestedStructure($data)) {
            $flattened = $this->flattener->flattenNestedStructure($data);
            unset($flattened['_idMappings']);

            return $flattened['blocks'] ?? [];
        }

        return $data['blocks'] ?? [];
    }

    /**
     * Clear all loaded blocks (useful for testing).
     */
    public function clear(): void
    {
        self::$loadedBlocks = [];
    }
}
