<?php

declare(strict_types=1);

namespace Craftile\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void loadFile(string $sourceFilePath)
 * @method static \Craftile\Laravel\BlockData|null getBlock(string $blockId, array $defaults = [])
 * @method static bool hasBlock(string $blockId)
 * @method static array getBlocksArray(string $sourceFilePath)
 * @method static void clear()
 *
 * @see \Craftile\Laravel\BlockDatastore
 */
class BlockDatastore extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Craftile\Laravel\BlockDatastore::class;
    }
}
