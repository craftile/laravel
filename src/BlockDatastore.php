<?php

namespace Craftile\Laravel;

use Craftile\Laravel\Facades\Craftile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Yaml\Yaml;

class BlockDatastore
{
    /**
     * In-memory cache of loaded BlockData instances
     * Structure: ['blockId' => BlockData].
     */
    private static array $loadedBlocks = [];

    /**
     * In-memory cache of parsed file data
     * Structure: ['filePath' => ['blocks' => [...]]].
     */
    private static array $parsedFiles = [];

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
     * Get all blocks array from the given JSON file.
     * Uses in-memory caching in preview mode, Laravel cache otherwise.
     */
    public function getBlocksArray(string $sourceFilePath): array
    {
        if (isset(self::$parsedFiles[$sourceFilePath])) {
            return self::$parsedFiles[$sourceFilePath];
        }

        $blocks = null;

        // In preview mode, use only in-memory caching
        if (Craftile::inPreview()) {
            $blocks = $this->parseBlocksFromFile($sourceFilePath);
        } else {
            // In production mode, use Laravel cache
            $cacheKey = 'craftile_blocks_'.md5($sourceFilePath.'_'.filemtime($sourceFilePath));
            $cacheTtl = config('craftile.cache.ttl', 3600);
            $blocks = Cache::remember($cacheKey, $cacheTtl, function () use ($sourceFilePath) {
                return $this->parseBlocksFromFile($sourceFilePath);
            });
        }

        self::$parsedFiles[$sourceFilePath] = $blocks;

        return $blocks;
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

        $template = [];

        if ($this->flattener->hasNestedStructure($data)) {
            $template = $this->flattener->flattenNestedStructure($data);
            unset($template['_idMappings']);
        } else {
            $template = $data;
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
        self::$parsedFiles = [];
    }
}
