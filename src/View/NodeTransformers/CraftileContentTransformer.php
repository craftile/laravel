<?php

namespace Craftile\Laravel\View\NodeTransformers;

use Craftile\Laravel\Contracts\NodeTransformerInterface;
use Craftile\Laravel\Support\DirectiveVariants;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesLiterals;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;
use Stillat\BladeParser\Nodes\DirectiveNode;

class CraftileContentTransformer implements NodeTransformerInterface
{
    use HandlesErrors, HandlesLiterals;

    public function supports(AbstractNode $node): bool
    {
        return $this->isLayoutContentDirective($node) ||
               $this->isLayoutContentTag($node) ||
               $this->isContentDirective($node) ||
               $this->isContentTag($node) ||
               $this->isEndContentDirective($node) ||
               $this->isEndContentTag($node);
    }

    public function transform(AbstractNode $node, ?Document $document): AbstractNode
    {
        return match (true) {
            $this->isLayoutContentDirective($node) => $this->transformLayoutContentDirective($node),
            $this->isLayoutContentTag($node) => $this->transformLayoutContentTag($node),
            $this->isContentDirective($node) => $this->transformContentDirective($node),
            $this->isContentTag($node) => $this->transformContentTag($node),
            $this->isEndContentDirective($node) => $this->transformEndContentDirective($node),
            $this->isEndContentTag($node) => $this->transformEndContentTag($node),
            default => $node
        };
    }

    // Layout content detection
    protected function isLayoutContentDirective(AbstractNode $node): bool
    {
        if (! ($node instanceof DirectiveNode)) {
            return false;
        }

        $directives = config('craftile.directives', []);
        $layoutContentDirective = $directives['craftileLayoutContent'] ?? 'craftileLayoutContent';
        $variants = DirectiveVariants::generate($layoutContentDirective);

        return in_array($node->content, $variants);
    }

    protected function isLayoutContentTag(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
               $node->componentPrefix === $namespace &&
               ($node->tagName === 'layout-content' || $node->tagName === 'layoutcontent');
    }

    // Content section detection
    protected function isContentDirective(AbstractNode $node): bool
    {
        if (! ($node instanceof DirectiveNode)) {
            return false;
        }

        $directives = config('craftile.directives', []);
        $contentDirective = $directives['craftileContent'] ?? 'craftileContent';
        $variants = DirectiveVariants::generate($contentDirective);

        return in_array($node->content, $variants);
    }

    protected function isContentTag(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
               $node->componentPrefix === $namespace &&
               $node->tagName === 'content' &&
               ! $node->isClosingTag;
    }

    protected function isEndContentDirective(AbstractNode $node): bool
    {
        if (! ($node instanceof DirectiveNode)) {
            return false;
        }

        $directives = config('craftile.directives', []);
        $contentDirective = $directives['craftileContent'] ?? 'craftileContent';
        $endVariants = DirectiveVariants::generateEnd($contentDirective);

        return in_array($node->content, $endVariants);
    }

    protected function isEndContentTag(AbstractNode $node): bool
    {
        $namespace = config('craftile.components.namespace', 'craftile');

        return $node instanceof ComponentNode &&
               $node->componentPrefix === $namespace &&
               $node->tagName === 'content' &&
               $node->isClosingTag;
    }

    // Transformations
    protected function transformLayoutContentDirective(AbstractNode $node): AbstractNode
    {
        $compiled = "<?php craftile()->beforeContent(); ?>@yield('craftileContent')<?php craftile()->afterContent(); ?>";

        return $this->createLiteralNode($compiled);
    }

    protected function transformLayoutContentTag(AbstractNode $node): AbstractNode
    {
        if ($node instanceof ComponentNode && ! empty($node->parameters)) {
            $namespace = config('craftile.components.namespace', 'craftile');
            $this->throwError("<{$namespace}:layout-content> tag should not have any attributes", $node);
        }

        $compiled = "<?php craftile()->beforeContent(); ?>@yield('craftileContent')<?php craftile()->afterContent(); ?>";

        return $this->createLiteralNode($compiled);
    }

    protected function transformContentDirective(AbstractNode $node): AbstractNode
    {
        $compiled = "<?php craftile()->startContent(); ?>\n@section('craftileContent')";

        return $this->createLiteralNode($compiled);
    }

    protected function transformContentTag(AbstractNode $node): AbstractNode
    {
        if ($node instanceof ComponentNode && ! empty($node->parameters)) {
            $namespace = config('craftile.components.namespace', 'craftile');
            $this->throwError("<{$namespace}:content> tag should not have any attributes", $node);
        }

        $compiled = "<?php craftile()->startContent(); ?>\n@section('craftileContent')";

        return $this->createLiteralNode($compiled);
    }

    protected function transformEndContentDirective(AbstractNode $node): AbstractNode
    {
        $compiled = "@endsection\n<?php craftile()->endContent(); ?>";

        return $this->createLiteralNode($compiled);
    }

    protected function transformEndContentTag(AbstractNode $node): AbstractNode
    {
        $compiled = "@endsection\n<?php craftile()->endContent(); ?>";

        return $this->createLiteralNode($compiled);
    }
}
