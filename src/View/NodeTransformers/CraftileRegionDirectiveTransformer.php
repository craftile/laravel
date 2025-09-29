<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\Support\DirectiveVariants;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\DirectiveNode;

class CraftileRegionDirectiveTransformer implements NodeTransformerInterface
{
    use HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        if (! $node instanceof DirectiveNode) {
            return false;
        }

        // Get configured directive names and generate all variants
        $directives = config('craftile.directives', []);
        $regionDirective = $directives['craftileRegion'] ?? 'craftileRegion';
        $variants = DirectiveVariants::generate($regionDirective);

        return in_array($node->content, $variants);
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        if (! $node instanceof DirectiveNode) {
            return $node;
        }

        $directives = config('craftile.directives', []);
        $regionDirective = $directives['craftileRegion'] ?? 'craftileRegion';

        if ($node->arguments === null) {
            $this->throwError("@{$regionDirective} requires one argument: region name", $node);
        }

        $args = $node->arguments->getArgValues();

        if ($args->count() < 1) {
            $this->throwError("@{$regionDirective} requires one argument: region name", $node);
        }

        $regionNameExpr = $args[0];

        // Transform to @includeIf directive
        $compiled = "@includeIf(craftile()->resolveRegionView({$regionNameExpr}))";

        return $this->createLiteralNode($compiled);
    }
}
