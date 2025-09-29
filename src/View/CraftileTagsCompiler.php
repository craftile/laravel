<?php

namespace Craftile\Laravel\View;

use Craftile\Laravel\Support\DirectiveVariants;
use Craftile\Laravel\View\NodeTransformers\Concerns\HandlesErrors;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Parser\DocumentParser;

class CraftileTagsCompiler extends ComponentTagCompiler
{
    use HandlesErrors;

    protected ?Document $document = null;

    /**
     * Make the precompiler invokable.
     */
    public function __invoke(string $template): string
    {
        $this->aliases = $this->blade->getClassComponentAliases();
        $this->namespaces = $this->blade->getClassComponentNamespaces();

        $directives = config('craftile.directives', []);
        $namespace = config('craftile.components.namespace', 'craftile');

        $allDirectives = [];

        // Block directive variants
        $blockDirective = $directives['craftileBlock'] ?? 'craftileBlock';
        $allDirectives = array_merge($allDirectives, DirectiveVariants::generate($blockDirective));

        // Region directive variants
        $regionDirective = $directives['craftileRegion'] ?? 'craftileRegion';
        $allDirectives = array_merge($allDirectives, DirectiveVariants::generate($regionDirective));

        // Content directive variants
        $contentDirective = $directives['craftileContent'] ?? 'craftileContent';
        $layoutContentDirective = $directives['craftileLayoutContent'] ?? 'craftileLayoutContent';

        $allDirectives = array_merge(
            $allDirectives,
            DirectiveVariants::generate($contentDirective),
            DirectiveVariants::generate($layoutContentDirective),
            DirectiveVariants::generateEnd($contentDirective)
        );

        // Parse template and resolve structures
        $parser = new DocumentParser;
        $parser->setDirectiveNames($allDirectives);
        $parser->registerCustomComponentTags([$namespace]);
        $parser->parse($template);

        $this->document = $parser->toDocument();
        $this->document->resolveStructures();

        $nodes = $this->document->getNodes()->all();

        $transformerRegistry = app(NodeTransformerRegistry::class);

        $nodes = array_map(function ($node) use ($transformerRegistry) {
            return $transformerRegistry->transform($node, $this->document, $this);
        }, $nodes);

        $newTemplate = implode('', array_map(fn ($node) => $node->toString(), $nodes));

        return $newTemplate;
    }
}
