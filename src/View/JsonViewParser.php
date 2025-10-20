<?php

namespace Craftile\Laravel\View;

use Craftile\Laravel\Facades\Craftile;
use Craftile\Laravel\View\TemplatePipeline\ApplyUserNormalizer;
use Craftile\Laravel\View\TemplatePipeline\EnsureBlockIds;
use Craftile\Laravel\View\TemplatePipeline\EnsureRegionsFormat;
use Craftile\Laravel\View\TemplatePipeline\FlattenNestedStructure;
use Craftile\Laravel\View\TemplatePipeline\ParseTemplateFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Pipeline;

class JsonViewParser
{
    /**
     * In-memory cache of parsed template data.
     */
    private static array $cache = [];

    /**
     * Parse and normalize template file with automatic caching.
     *
     * @param  string  $path  Path to template file
     * @return array Fully normalized template data
     */
    public function parse(string $path): array
    {
        // Check in-memory cache first
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        if (Craftile::inPreview()) {
            $data = $this->runPipeline($path);
        } else {
            $cacheKey = 'craftile_template_'.md5($path.'_'.filemtime($path));
            $cacheTtl = config('craftile.cache.ttl', 3600);
            $data = Cache::remember($cacheKey, $cacheTtl, fn () => $this->runPipeline($path));
        }

        // Store in in-memory cache
        self::$cache[$path] = $data;

        return $data;
    }

    /**
     * Run the normalization pipeline.
     *
     * @param  string  $path  File path
     * @return array Normalized template data
     */
    protected function runPipeline(string $path): array
    {
        return Pipeline::send($path)
            ->through([
                ParseTemplateFile::class,
                ApplyUserNormalizer::class,
                EnsureBlockIds::class,
                EnsureRegionsFormat::class,
                FlattenNestedStructure::class,
            ])
            ->thenReturn();
    }

    /**
     * Clear all cached parsed files (useful for testing).
     */
    public function clearCache(): void
    {
        self::$cache = [];
    }
}
