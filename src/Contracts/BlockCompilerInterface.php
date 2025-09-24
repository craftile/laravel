<?php

namespace Craftile\Laravel\Contracts;

use Craftile\Core\Data\BlockSchema;

interface BlockCompilerInterface
{
    /**
     * Determine if this compiler supports the given block schema.
     */
    public function supports(BlockSchema $schema): bool;

    /**
     * Compile block data into Blade/PHP code.
     */
    public function compile(string $blockType, string $hash, string $childrenClosureCode = '', string $customAttributesExpr = ''): string;
}
