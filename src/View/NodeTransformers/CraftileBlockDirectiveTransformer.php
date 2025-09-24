<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\ComponentCompilerAwareInterface;
use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\Support\DirectiveVariants;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesCraftileBlocks;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\DirectiveNode;

class CraftileBlockDirectiveTransformer implements NodeTransformerInterface, ComponentCompilerAwareInterface
{
    use HandlesCraftileBlocks, HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        if (! $node instanceof DirectiveNode) {
            return false;
        }

        // Get configured directive names and generate all variants
        $directives = config('craftile.directives', []);
        $blockDirective = $directives['craftileBlock'] ?? 'craftileBlock';
        $variants = DirectiveVariants::generate($blockDirective);

        return in_array($node->content, $variants);
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        if (! $node instanceof DirectiveNode) {
            return $node;
        }

        $directives = config('craftile.directives', []);
        $blockDirective = $directives['craftileBlock'] ?? 'craftileBlock';

        if ($node->arguments === null) {
            $this->throwError("@{$blockDirective} requires at least two arguments: type and id", $node);
        }

        $args = $node->arguments->getArgValues();

        if ($args->count() < 2) {
            $this->throwError("@{$blockDirective} requires at least two arguments: type and id", $node);
        }

        $typeExpr = $args[0];
        $idExpr = $args[1];
        $dataExpr = $args->count() > 2 ? $args[2] : '[]';

        $type = $this->unquoteLiteral($typeExpr);
        if ($type === null) {
            $this->throwError("First @{$blockDirective} argument (type) must be a literal string.", $node);
        }

        $id = $this->unquoteLiteral($idExpr);
        if ($id === null) {
            $this->throwError("Second @{$blockDirective} argument (id) must be a literal string.", $node);
        }

        $compiled = $this->compileBlock($type, $id, $dataExpr, $node, $document);

        return $this->createLiteralNode($compiled);
    }

}
