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

    protected $templateNormalizer = null;

    public function __construct(
        protected BlockSchemaRegistry $schemaRegistry,
        protected PropertyTransformerRegistry $transformerRegistry,
        protected BlockDiscovery $blockDiscovery,
        protected PreviewDataCollector $previewDataCollector,
        protected BlockDatastore $blockDatastore
    ) {}

    /**
     * Discover blocks in a directory.
     */
    public function discoverBlocksIn(string $namespace, string $directory): void
    {
        $this->blockDiscovery->scan($namespace, $directory);
    }

    /**
     * Register a block by its class name.
     *
     * @param  string  $blockClass  Block class that implements BlockInterface
     *
     * @throws \InvalidArgumentException If class doesn't exist or doesn't implement BlockInterface
     */
    public function registerBlock(string $blockClass): void
    {
        if (! is_subclass_of($blockClass, \Craftile\Core\Contracts\BlockInterface::class)) {
            throw new \InvalidArgumentException("Block class {$blockClass} must implement BlockInterface");
        }

        $schemaClass = $this->getBlockSchemaClass();
        $schema = $schemaClass::fromClass($blockClass);

        $this->schemaRegistry->register($schema);
    }

    /**
     * Register multiple blocks at once.
     *
     * @param  array<string>  $blockClasses  Array of block classes
     */
    public function registerBlocks(array $blockClasses): void
    {
        foreach ($blockClasses as $blockClass) {
            $this->registerBlock($blockClass);
        }
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
     * Register a custom template normalizer.
     */
    public function normalizeTemplateUsing(callable $normalizer): void
    {
        $this->templateNormalizer = $normalizer;
    }

    /**
     * Normalize template data using the registered normalizer.
     */
    public function normalizeTemplate(array $templateData): array
    {
        if ($this->templateNormalizer) {
            return call_user_func($this->templateNormalizer, $templateData);
        }

        return $templateData;
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
     * Get the configured BlockSchema class.
     */
    public function getBlockSchemaClass(): string
    {
        $schemaClass = config('craftile.block_schema_class', BlockSchema::class);

        if (! is_subclass_of($schemaClass, BlockSchema::class) && $schemaClass !== BlockSchema::class) {
            throw new \InvalidArgumentException("BlockSchema class '{$schemaClass}' must extend Craftile\\Core\\Data\\BlockSchema");
        }

        return $schemaClass;
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
     * Resolve static block by semanticId within parent context
     * Used for static block template resolution after duplication.
     */
    public function resolveStaticBlockId(string $parentId, string $semanticId): ?string
    {
        $allBlocks = $this->blockDatastore->getAllBlocks();

        foreach ($allBlocks as $blockId => $block) {
            if ($block->static && $block->parentId === $parentId) {
                $blockSemanticId = $block->semanticId ?? $block->id;
                if ($blockSemanticId === $semanticId) {
                    return $blockId;
                }
            }
        }

        return null;
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
                && ! in_array($key, ['app', 'errors', 'block', 'section'], true),
            ARRAY_FILTER_USE_BOTH
        );

        return array_merge($filtered, $customAttributes);
    }
}
