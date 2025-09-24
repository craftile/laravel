<?php

namespace Craftile\Laravel\View;

use Craftile\Laravel\Contracts\ComponentCompilerAwareInterface;
use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;

class NodeTransformerRegistry
{
    /**
     * @var NodeTransformerInterface[]
     */
    private array $transformers = [];

    /**
     * Register a node transformer
     */
    public function register(NodeTransformerInterface $transformer): void
    {
        $this->transformers[] = $transformer;
    }

    /**
     * Find the first transformer that supports the given node
     */
    public function findTransformer(AbstractNode $node): ?NodeTransformerInterface
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->supports($node)) {
                return $transformer;
            }
        }

        return null;
    }

    /**
     * Transform a node using the first matching transformer
     */
    public function transform(AbstractNode $node, ?Document $document = null, ?ComponentTagCompiler $componentCompiler = null): AbstractNode
    {
        $transformer = $this->findTransformer($node);

        if ($transformer) {
            if ($transformer instanceof ComponentCompilerAwareInterface) {
                $transformer->setComponentCompiler($componentCompiler);
            }

            return $transformer->transform($node, $document);
        }

        return $node;
    }

    /**
     * Get all registered transformers
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }
}
