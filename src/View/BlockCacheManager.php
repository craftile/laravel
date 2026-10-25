<?php

namespace Craftile\Laravel\View;

use Craftile\Laravel\BlockData;
use Illuminate\Filesystem\Filesystem;

class BlockCacheManager
{
    private Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Generate cache key for a block including block ID and content hash.
     */
    public function getCacheKey(array $blockData): string
    {
        $blockId = $blockData['id'] ?? 'unknown';
        $contentHash = hash('xxh128', json_encode($blockData, JSON_UNESCAPED_SLASHES));

        return "{$blockId}-{$contentHash}";
    }

    /**
     * Read cached compiled block content by cache key.
     */
    public function get(string $cacheKey): ?string
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if ($this->files->exists($cacheFile)) {
            return $this->files->get($cacheFile);
        }

        return null;
    }

    /**
     * Write compiled block content to cache by cache key.
     */
    public function put(string $cacheKey, string $content): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        $this->files->ensureDirectoryExists(dirname($cacheFile));

        return $this->files->put($cacheFile, $content) !== false;
    }

    /**
     * Check if cache exists for a block with specific cache key.
     */
    public function exists(string $cacheKey): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        return $this->files->exists($cacheFile);
    }

    /**
     * Delete cache file for a specific block by cache key.
     */
    public function delete(string $cacheKey): bool
    {
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if ($this->files->exists($cacheFile)) {
            return $this->files->delete($cacheFile);
        }

        return false;
    }

    /**
     * Delete all cache files for a specific block ID.
     *
     * Children files are kept: they are keyed by content and required by compiled views at render time.
     */
    public function flushBlock(string $blockId): bool
    {
        $basePath = config('view.compiled');
        $pattern = "{$basePath}/craftile-{$blockId}-*.php";

        $files = glob($pattern);
        if (! $files) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (! $this->files->delete($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Generate a key for a block's children from what their compiled code depends on.
     *
     * Settings are read at render time, so only the order, id, type, static and ghost flags matter.
     */
    public function childrenKey(array $children): string
    {
        $signature = array_map(fn (array $child) => [
            (string) ($child['id'] ?? ''),
            (string) ($child['type'] ?? ''),
            (bool) ($child['static'] ?? false),
            (bool) ($child['ghost'] ?? false),
        ], $children);

        return hash('xxh128', json_encode(array_values($signature), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the compiled children file path for a block.
     *
     * Calling it without a children key is deprecated and only kept for views compiled by older versions.
     */
    public function getChildrenFilePath(string $blockId, ?string $childrenKey = null): string
    {
        $basePath = config('view.compiled');
        $key = $childrenKey ?? hash('xxh128', $blockId);

        return "{$basePath}/craftile-children-{$blockId}-{$key}.php";
    }

    /**
     * Get the compiled children file path for a block being rendered.
     *
     * Returns null when the block has no children or one of them is not loaded.
     */
    public function getChildrenFilePathForBlock(BlockData $block): ?string
    {
        if (empty($block->childrenIds())) {
            return null;
        }

        $children = [];
        foreach ($block->children() as $child) {
            if (! $child) {
                return null;
            }

            $children[] = [
                'id' => $child->id,
                'type' => $child->type,
                'static' => $child->static,
                'ghost' => $child->ghost,
            ];
        }

        return $this->getChildrenFilePath($block->id, $this->childrenKey($children));
    }

    /**
     * Write compiled children code to file.
     */
    public function writeChildrenFile(string $blockId, string $childrenCode, ?string $childrenKey = null): string
    {
        $filePath = $this->getChildrenFilePath($blockId, $childrenKey);

        $this->files->replace($filePath, $childrenCode);

        return $filePath;
    }

    /**
     * Delete all compiled children files for a specific block ID.
     *
     * Not used during compilation: compiled views of other templates may still require these files.
     */
    public function flushChildrenFile(string $blockId): bool
    {
        $basePath = config('view.compiled');
        $pattern = "{$basePath}/craftile-children-{$blockId}-*.php";

        $files = glob($pattern);
        if (! $files) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (! $this->files->delete($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get the cache file path for a given cache key.
     */
    private function getCacheFilePath(string $cacheKey): string
    {
        $basePath = config('view.compiled');

        return "{$basePath}/craftile-{$cacheKey}.php";
    }

    /**
     * Clear all Craftile cache files.
     */
    public function flush(): bool
    {
        $basePath = config('view.compiled');
        $pattern = "{$basePath}/craftile-*.php";

        $files = glob($pattern);
        if (! $files) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (! $this->files->delete($file)) {
                $success = false;
            }
        }

        return $success;
    }
}
