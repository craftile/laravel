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
use Stillat\BladeParser\Nodes\Components\ParameterType;

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
        $keepExpr = $this->extractKeepExpr($node);

        $compiled = $this->compileBlock($type, $id, $propertiesExpr, $node, $document, $keepExpr);

        return $this->createLiteralNode($compiled);
    }

    /**
     * Extract the keep attribute as a PHP expression: bare `keep` means true, `:keep="expr"` passes the expression.
     */
    protected function extractKeepExpr(ComponentNode $node): string
    {
        $attribute = collect($node->parameters)->firstWhere(fn ($param) => $param->materializedName === 'keep');

        if (! $attribute) {
            return 'false';
        }

        $value = $attribute->value;

        if ($attribute->type === ParameterType::DynamicVariable ||
            $attribute->type === ParameterType::ShorthandDynamicVariable) {
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") &&
                $value[0] === $value[strlen($value) - 1]) {
                $value = substr($value, 1, -1);
            }

            return $value;
        }

        if ($value === null || $value === '') {
            return 'true';
        }

        if ($this->isBladeLiteral($value)) {
            $value = $attribute->valueNode->content;
        }

        return in_array(strtolower(trim($value, '"\'')), ['false', '0', ''], true) ? 'false' : 'true';
    }
}
