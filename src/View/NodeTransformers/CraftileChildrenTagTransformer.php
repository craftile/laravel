<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;
use Stillat\BladeParser\Nodes\DirectiveNode;

class CraftileChildrenTagTransformer implements NodeTransformerInterface
{
    use HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        return $this->isChildrenDirective($node) || $this->isChildrenTag($node);
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        return match (true) {
            $this->isChildrenDirective($node) => $this->transformChildrenDirective($node),
            $this->isChildrenTag($node) => $this->transformChildrenTag($node),
            default => $node
        };
    }

    protected function isChildrenDirective(AbstractNode $node): bool
    {
        return $node instanceof DirectiveNode && $node->content === 'children';
    }

    protected function isChildrenTag(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
            $node->componentPrefix === $namespace &&
            $node->tagName === 'children';
    }

    protected function transformChildrenDirective(AbstractNode $node): AbstractNode
    {
        return $this->createLiteralNode($this->getCompiledCode());
    }

    protected function transformChildrenTag(AbstractNode $node): AbstractNode
    {
        if ($node instanceof ComponentNode && ! empty($node->parameters)) {
            $namespace = config('craftile.components.namespace', 'craftile');
            $this->throwError("<{$namespace}:children> tag should not have any attributes", $node);
        }

        return $this->createLiteralNode($this->getCompiledCode());
    }

    protected function getCompiledCode(): string
    {
        return '<?php
            $__childrenFilePath = app(\\Craftile\\Laravel\\View\\BlockCacheManager::class)->getChildrenFilePath($block->id);
            if(file_exists($__childrenFilePath)) {
                // Extract parent context if available
                if (isset($__craftileContext)) {
                    extract($__craftileContext);
                }
                require $__childrenFilePath;
            }
        ?>';
    }
}
