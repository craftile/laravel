<?php

namespace Craftile\Laravel\Contracts;

use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;

interface NodeTransformerInterface
{
    /**
     * Determine if this transformer can handle the given node.
     */
    public function supports(AbstractNode $node): bool;

    /**
     * Transform the node into a processed AbstractNode.
     */
    public function transform(AbstractNode $node, ?Document $document): AbstractNode;
}
