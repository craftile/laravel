<?php

namespace Craftile\Laravel\View\Compilers;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\Contracts\BlockCompilerInterface;

class DefaultBlockCompiler implements BlockCompilerInterface
{
    public function supports(BlockSchema $schema): bool
    {
        return true;
    }

    public function compile(string $blockType, string $hash, string $childrenClosureCode = '', string $customAttributesExpr = '[]'): string
    {
        $schemaVar = '$__blockSchema'.$hash;
        $instanceVar = '$__blockInstance'.$hash;
        $blockDataVar = '$__blockData'.$hash;
        $contextVar = '$__context'.$hash;
        $viewVar = '$__blockView'.$hash;
        $viewDataVar = '$__blockViewData'.$hash;
        $childrenVar = '$__children'.$hash;

        $childrenCode = $childrenClosureCode ? "{$childrenVar} = {$childrenClosureCode};" : "{$childrenVar} = null;";

        return <<<PHP
        <?php
        $childrenCode
        $schemaVar = craftile()->getBlockSchema("{$blockType}");
        $instanceVar = new {$schemaVar}->class;

        {$contextVar} = array_merge(
            array_filter(get_defined_vars(), fn(\$_, \$key) => !str_starts_with(\$key, '__') || \$key === '__staticBlocksChildren', ARRAY_FILTER_USE_BOTH),
            {$customAttributesExpr}
        );

        if (method_exists({$instanceVar}, 'setBlockData')) {
            {$instanceVar}->setBlockData({$blockDataVar});
        }

        if (method_exists({$instanceVar}, 'setContext')) {
            {$instanceVar}->setContext({$contextVar});
        }

        {$viewVar} = {$instanceVar}->render();

        if({$viewVar} instanceof \\Illuminate\\View\\View) {
            {$viewDataVar} = array_merge(
                {$contextVar},
                ['block' => {$blockDataVar}, 'children' => {$childrenVar}]
            );

            echo {$viewVar}->with({$viewDataVar})->render();

            unset({$viewDataVar});
        } else {
            echo {$viewVar};
        }

        unset({$schemaVar}, {$instanceVar}, {$viewVar});
        ?>
        PHP;
    }
}
