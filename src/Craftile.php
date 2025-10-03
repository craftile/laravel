<?php

namespace Craftile\Laravel;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\Contracts\PropertyTransformerInterface;

class Craftile
{
    protected $previewDetector = null;

    protected ?bool $previewModeCache = null;

    protected $regionViewResolver = null;

    protected $renderBlockChecker = null;

    protected $blockDataFactory = null;

    public function __construct(
        protected BlockSchemaRegistry $schemaRegistry,
        protected PropertyTransformerRegistry $transformerRegistry,
        protected BlockDiscovery $blockDiscovery,
        protected PreviewDataCollector $previewDataCollector
    ) {}

    /**
     * Discover blocks in a directory.
     */
    public function discoverBlocksIn(string $namespace, string $directory): void
    {
        $this->blockDiscovery->scan($namespace, $directory);
    }

    /**
     * Register a custom preview mode detector.
     */
    public function detectPreviewUsing(callable $detector): void
    {
        $this->previewDetector = $detector;
        $this->previewModeCache = null;
    }

    /**
     * Register a custom block render checker.
     */
    public function checkIfBlockCanRenderUsing(callable $checker): void
    {
        $this->renderBlockChecker = $checker;
    }

    /**
     * Register a custom BlockData factory.
     */
    public function createBlockDataUsing(callable $factory): void
    {
        $this->blockDataFactory = $factory;
    }

    /**
     * Check if currently in preview mode.
     */
    public function inPreview(): bool
    {
        if ($this->previewModeCache !== null) {
            return $this->previewModeCache;
        }

        if ($this->previewDetector) {
            return $this->previewModeCache = call_user_func($this->previewDetector);
        }

        // Fall back to default query parameter detection
        $parameter = config('craftile.preview.query_parameter', '_preview');

        return $this->previewModeCache = request()->has($parameter);
    }

    /**
     * Start tracking a region (delegates to PreviewDataCollector).
     */
    public function startRegion(string $regionName): void
    {
        $this->previewDataCollector->startRegion($regionName);
    }

    /**
     * End tracking a region (delegates to PreviewDataCollector).
     */
    public function endRegion(string $regionName): void
    {
        $this->previewDataCollector->endRegion($regionName);
    }

    /**
     * Start tracking a block (delegates to PreviewDataCollector).
     */
    public function startBlock(string $blockId, $blockContext): void
    {
        $this->previewDataCollector->startBlock($blockId, $blockContext);
    }

    /**
     * End tracking a block (delegates to PreviewDataCollector).
     */
    public function endBlock(string $blockId): void
    {
        $this->previewDataCollector->endBlock($blockId);
    }

    /**
     * Check if a block should be rendered.
     */
    public function shouldRenderBlock(BlockData $blockData): bool
    {
        if ($this->renderBlockChecker) {
            return call_user_func($this->renderBlockChecker, $blockData);
        }

        return ! $blockData->disabled;
    }

    /**
     * Create a BlockData instance using factory or config.
     */
    public function createBlockData(array $blockData, mixed $resolveChildData = null): BlockData
    {
        if ($this->blockDataFactory) {
            return call_user_func($this->blockDataFactory, $blockData, $resolveChildData);
        }

        $blockDataClass = config('craftile.block_data_class', BlockData::class);

        if (! is_subclass_of($blockDataClass, BlockData::class) && $blockDataClass !== BlockData::class) {
            throw new \InvalidArgumentException("BlockData class '{$blockDataClass}' must extend Craftile\Laravel\BlockData");
        }

        return $blockDataClass::make($blockData, $resolveChildData);
    }

    /**
     * Start tracking content region (page-specific content).
     */
    public function startContent(): void
    {
        $this->previewDataCollector->startContent();
    }

    /**
     * End tracking content region (page-specific content).
     */
    public function endContent(): void
    {
        $this->previewDataCollector->endContent();
    }

    /**
     * Mark the start of main content area.
     */
    public function beforeContent(): void
    {
        $this->previewDataCollector->beforeContent();
    }

    /**
     * Mark the end of main content area.
     */
    public function afterContent(): void
    {
        $this->previewDataCollector->afterContent();
    }

    /**
     * Get block schema by type.
     */
    public function getBlockSchema(string $type): ?BlockSchema
    {
        return $this->schemaRegistry->get($type);
    }

    /**
     * Register a property transformer for a given type.
     */
    public function registerPropertyTransformer(string $type, PropertyTransformerInterface|callable $transformer): void
    {
        $this->transformerRegistry->register($type, $transformer);
    }

    /**
     * Generate a unique hash-based ID for a child block
     */
    public function generateChildId(string $parentId, string $childLocalId): string
    {
        $contextHash = substr(hash('sha256', $parentId.'.'.$childLocalId), 0, 8);

        return "{$childLocalId}_{$contextHash}";
    }

    /**
     * Register a custom region view resolver.
     */
    public function resolveRegionViewUsing(callable $resolver): void
    {
        $this->regionViewResolver = $resolver;
    }

    /**
     * Resolve a region view name.
     */
    public function resolveRegionView(string $regionName): string
    {
        if ($this->regionViewResolver) {
            return call_user_func($this->regionViewResolver, $regionName);
        }

        $prefix = config('craftile.region_view_prefix', 'regions');

        return "{$prefix}.{$regionName}";
    }

    /**
     * Filter context variables for blocks.
     *
     * Filters out:
     * - Variables starting with '__' (except '__staticBlocksChildren')
     * - Laravel auto-injected variables ('app', 'errors')
     *
     * @param  array  $vars  The variables to filter (typically from get_defined_vars())
     * @param  array  $customAttributes  Additional custom attributes to merge
     * @return array Filtered context
     */
    public function filterContext(array $vars, array $customAttributes = []): array
    {
        $filtered = array_filter(
            $vars,
            fn ($_, $key) => (! str_starts_with($key, '__') || $key === '__staticBlocksChildren')
                && ! in_array($key, ['app', 'errors'], true),
            ARRAY_FILTER_USE_BOTH
        );

        return array_merge($filtered, $customAttributes);
    }
}
