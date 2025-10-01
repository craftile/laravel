<?php

namespace Craftile\Laravel;

use Craftile\Laravel\Events\JsonViewLoaded;
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

        JsonViewLoaded::dispatch($sourceFilePath);

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

        $mergedBlock = Craftile::createBlockData(
            $mergedData,
            fn ($childId) => self::$loadedBlocks[$childId] ?? null
        );
        $mergedBlock->setSourceFile($existingBlock->getSourceFile());

        return $mergedBlock;
    }

    /**
     * Check if a block exists in memory cache.
     */
    public function hasBlock(string $blockId): bool
    {
        return isset(self::$loadedBlocks[$blockId]);
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

        // Check if the data contains nested block structures and flatten if needed
        if ($this->flattener->hasNestedStructure($data)) {
            $flattened = $this->flattener->flattenNestedStructure($data);
            unset($flattened['_idMappings']);

            return $flattened['blocks'] ?? [];
        }

        return $data['blocks'] ?? [];
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
