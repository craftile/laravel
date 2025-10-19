<?php

namespace Craftile\Laravel\View\Compilers;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\Contracts\BlockCompilerInterface;

class BladeComponentBlockCompiler implements BlockCompilerInterface
{
    public function supports(BlockSchema $schema): bool
    {
        return is_subclass_of($schema->class, \Illuminate\View\Component::class);
    }

    public function compile(BlockSchema $schema, string $hash, string $customAttributesExpr = '[]'): string
    {
        $blockDataVar = '$__blockData'.$hash;
        $contextVar = '$__context'.$hash;

        return <<<PHP
        <?php
        // Root blocks (no parent) get page context and forward it child blocks via __craftileContext
        if ({$blockDataVar}->parentId === null) {
            {$contextVar} = craftile()->filterContext(get_defined_vars(), {$customAttributesExpr});
        } else {
            {$contextVar} = array_merge(
                isset(\$__craftileContext) ? \$__craftileContext : [],
                {$customAttributesExpr}
            );
        }

        // Inject context into PropertyBag for dynamic source resolution
        {$blockDataVar}->properties->setContext({$contextVar});
        ?>
        <x-craftile-{$schema->slug} :block="{$blockDataVar}" :context="{$contextVar}" />
        <?php
        // Clean up variables to free memory
        unset({$contextVar});
        ?>
        PHP;
    }
}
