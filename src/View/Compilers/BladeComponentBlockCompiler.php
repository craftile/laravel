<?php

namespace Craftile\Laravel\View\Compilers;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\Contracts\BlockCompilerInterface;

class BladeComponentBlockCompiler implements BlockCompilerInterface
{
    public function supports(BlockSchema $schema): bool
    {
        return $schema->class && is_subclass_of($schema->class, \Illuminate\View\Component::class);
    }

    public function compile(string $blockType, string $hash, string $childrenClosureCode = '', string $customAttributesExpr = '[]'): string
    {
        $blockDataVar = '$__blockData'.$hash;
        $childrenVar = '$__children'.$hash;
        $childrenCode = $childrenClosureCode ? "{$childrenVar} = {$childrenClosureCode};" : "{$childrenVar} = null;";

        $contextVar = '$__context'.$hash;

        return <<<PHP
        <?php
        {$childrenCode}
        {$contextVar} = array_merge(
            array_filter(get_defined_vars(), fn(\$_, \$key) => !str_starts_with(\$key, '__') || \$key === '__staticBlocksChildren', ARRAY_FILTER_USE_BOTH),
            {$customAttributesExpr}
        );
        ?>
        <x-craftile-{$blockType} :block="{$blockDataVar}" :context="{$contextVar}" :children="{$childrenVar}" />
        <?php
        // Clean up variables to free memory
        unset({$childrenVar}, {$contextVar});
        ?>
        PHP;
    }
}
