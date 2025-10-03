<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;

class CraftileChildrenTagTransformer implements NodeTransformerInterface
{
    use HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
            $node->componentPrefix === $namespace &&
            $node->tagName === 'children';
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        if (! empty($node->parameters)) {
            $namespace = config('craftile.components.namespace', 'craftile');
            $this->throwError("<{$namespace}:children> tag should not have any attributes", $node);
        }

        $compiled = '<?php if(isset($children) && is_callable($children)) {
            $__contextToPass = isset($__craftileContext) ? $__craftileContext : [];
            echo $children($__contextToPass);
        } ?>';

        return $this->createLiteralNode($compiled);
    }
}
