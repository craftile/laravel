<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\ComponentCompilerAwareInterface;
use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesComponentAttributes;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesCraftileBlocks;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;

class CraftileBlockTagTransformer implements ComponentCompilerAwareInterface, NodeTransformerInterface
{
    use HandlesComponentAttributes, HandlesCraftileBlocks, HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
            $node->componentPrefix === $namespace &&
            $node->tagName === 'block';
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        $type = $this->extractComponentAttributeValue($node, 'type');
        $id = $this->extractComponentAttributeValue($node, 'id');
        $propertiesExpr = $this->extractComponentPropertiesExpr($node);

        $compiled = $this->compileBlock($type, $id, $propertiesExpr, $node, $document);

        return $this->createLiteralNode($compiled);
    }
}
