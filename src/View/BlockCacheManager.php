<?php

namespace Craftile\Laravel\View;

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

        // Also flush children file for this block
        $this->flushChildrenFile($blockId);

        return $success;
    }

    /**
     * Get the compiled children file path for a block.
     */
    public function getChildrenFilePath(string $blockId): string
    {
        $basePath = config('view.compiled');
        $hash = hash('xxh128', $blockId);

        return "{$basePath}/craftile-children-{$blockId}-{$hash}.php";
    }

    /**
     * Write compiled children code to file.
     */
    public function writeChildrenFile(string $blockId, string $childrenCode): string
    {
        $filePath = $this->getChildrenFilePath($blockId);

        $this->files->put($filePath, $childrenCode);

        return $filePath;
    }

    /**
     * Delete the compiled children file for a specific block ID.
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
