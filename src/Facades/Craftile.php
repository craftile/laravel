<?php

declare(strict_types=1);

namespace Craftile\Laravel\Facades;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\BlockData;
use Craftile\Laravel\Contracts\PropertyTransformerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void discoverBlocksIn(string $namespace, string $directory)
 * @method static void detectPreviewUsing(callable $detector)
 * @method static void checkIfBlockCanRenderUsing(callable $checker)
 * @method static void createBlockDataUsing(callable $factory)
 * @method static void normalizeTemplateUsing(callable $normalizer)
 * @method static array normalizeTemplate(array $templateData)
 * @method static bool inPreview()
 * @method static bool shouldRenderBlock(BlockData $blockData)
 * @method static BlockData createBlockData(array $blockData, mixed $resolveChildData = null)
 * @method static string getBlockSchemaClass()
 * @method static BlockSchema|null getBlockSchema(string $type)
 * @method static void registerPropertyTransformer(string $type, PropertyTransformerInterface|callable $transformer)
 * @method static string generateChildId(string $parentId, string $childLocalId)
 * @method static void resolveRegionViewUsing(callable $resolver)
 * @method static string resolveRegionView(string $regionName)
 *
 * @see \Craftile\Laravel\Craftile
 */
class Craftile extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'craftile';
    }
}
