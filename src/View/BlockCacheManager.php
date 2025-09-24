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
     * Generate content hash for a block using the entire block data.
     */
    public function generateHash(array $blockData): string
    {
        return hash('xxh128', json_encode($blockData, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Read cached compiled block content by hash.
     */
    public function get(string $hash): ?string
    {
        $cacheFile = $this->getCacheFilePath($hash);

        if ($this->files->exists($cacheFile)) {
            return $this->files->get($cacheFile);
        }

        return null;
    }

    /**
     * Write compiled block content to cache by hash.
     */
    public function put(string $hash, string $content): bool
    {
        $cacheFile = $this->getCacheFilePath($hash);

        $this->files->ensureDirectoryExists(dirname($cacheFile));

        return $this->files->put($cacheFile, $content) !== false;
    }

    /**
     * Check if cache exists for a block with specific hash.
     */
    public function exists(string $hash): bool
    {
        $cacheFile = $this->getCacheFilePath($hash);

        return $this->files->exists($cacheFile);
    }

    /**
     * Delete cache file for a specific block by hash.
     */
    public function delete(string $hash): bool
    {
        $cacheFile = $this->getCacheFilePath($hash);

        if ($this->files->exists($cacheFile)) {
            return $this->files->delete($cacheFile);
        }

        return false;
    }

    /**
     * Get the cache file path for a given hash.
     */
    private function getCacheFilePath(string $hash): string
    {
        $basePath = config('view.compiled');

        return "{$basePath}/craftile-{$hash}.php";
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
