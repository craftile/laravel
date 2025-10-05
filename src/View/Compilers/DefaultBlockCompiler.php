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

    public function compile(BlockSchema $schema, string $hash, string $customAttributesExpr = '[]'): string
    {
        $instanceVar = '$__blockInstance'.$hash;
        $blockDataVar = '$__blockData'.$hash;
        $contextVar = '$__context'.$hash;
        $viewVar = '$__blockView'.$hash;
        $viewDataVar = '$__blockViewData'.$hash;

        return <<<PHP
        <?php
        $instanceVar = new \\{$schema->class};

        {$contextVar} = craftile()->filterContext(get_defined_vars(), {$customAttributesExpr});

        if (method_exists({$instanceVar}, 'setBlockData')) {
            {$instanceVar}->setBlockData({$blockDataVar});
        }

        if (method_exists({$instanceVar}, 'setContext')) {
            {$instanceVar}->setContext({$contextVar});
        }

        {$viewVar} = {$instanceVar}->render();

        if({$viewVar} instanceof \\Illuminate\\View\\View) {
            // Merge shared data from block instance if share() method exists
            \$__sharedData = method_exists({$instanceVar}, 'share') ? {$instanceVar}->share() : [];
            \$__mergedContext = array_merge({$contextVar}, \$__sharedData);

            {$viewDataVar} = array_merge(
                \$__mergedContext,
                ['block' => {$blockDataVar}, '__craftileContext' => \$__mergedContext]
            );

            echo {$viewVar}->with({$viewDataVar})->render();

            unset({$viewDataVar});
        } else {
            echo {$viewVar};
        }

        unset({$instanceVar}, {$viewVar});
        ?>
        PHP;
    }
}
