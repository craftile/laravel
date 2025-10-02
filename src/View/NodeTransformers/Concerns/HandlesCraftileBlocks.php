<?php

namespace Craftile\Laravel\View\NodeTransformers\Concerns;

use Craftile\Laravel\View\BlockCompilerRegistry;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\AbstractNode;
use Stillat\BladeParser\Nodes\DirectiveNode;

trait HandlesCraftileBlocks
{
    protected ?ComponentTagCompiler $componentCompiler = null;

    /**
     * Set the component compiler instance for proper Blade compilation.
     */
    public function setComponentCompiler(ComponentTagCompiler $componentCompiler): void
    {
        $this->componentCompiler = $componentCompiler;
    }

    /**
     * Compile a single @craftileBlock directive.
     *
     * For static blocks in loops, this method ensures:
     * - All iterations share the same block data (via semantic ID)
     * - Each iteration can have different custom attributes
     * - Preview collector only stores block data once per semantic ID
     * - Editor "select one, highlight all" behavior is preserved
     */
    protected function compileBlock(string $type, string $id, string $propertiesExpr, AbstractNode $node, ?Document $document): string
    {
        $schema = craftile()->getBlockSchema($type);

        if (! $schema) {
            $this->throwError('No block of type '.$type.' is registered', $node);
        }

        // Check if we're inside a loop for the repeated flag
        $isInLoop = $this->isNodeInsideLoop($node, $document);

        $hash = hash('xxh128', $id);
        $idVar = '$__blockId'.$hash;
        $blockDataVar = '$__blockData'.$hash;

        $compiler = app(BlockCompilerRegistry::class)->findCompiler($schema);

        // Mark blocks as repeated when in loops - used for editor behavior
        $repeatedExpr = $isInLoop ? 'true' : 'false';

        // Determine wrapper parts if schema has wrapper
        $wrapperOpening = '';
        $wrapperClosing = '';
        if ($schema->wrapper) {
            [$wrapperOpening, $wrapperClosing] = \Craftile\Laravel\View\WrapperCompiler::compileWrapper($schema->wrapper, $id);
        }

        $template = <<<PHP
        <?php
        if (isset(\$block)) {
            {$idVar} = craftile()->resolveStaticBlockId(\$block->id, '{$id}') ?? craftile()->generateChildId(\$block->id, '{$id}');
        } else {
            {$idVar} = '{$id}';
        }

        $blockDataVar = BlockDatastore::getBlock({$idVar}, ['static' => true, 'repeated' => {$repeatedExpr}]);

        if (!$blockDataVar) {
            $blockDataVar = craftile()->createBlockData(['id' => {$idVar}, 'type' => '{$type}', 'static' => true, 'repeated' => {$repeatedExpr}]);
        }

        // Get children closure from static blocks map if available
        \$childrenClosure = (isset(\$__staticBlocksChildren) && isset(\$__staticBlocksChildren[{$idVar}])) ? \$__staticBlocksChildren[{$idVar}] : '';
        ?>
        <?php if (craftile()->inPreview()) {
            craftile()->startBlock({$idVar}, $blockDataVar);
        } ?>

        {$wrapperOpening}
        {$compiler->compile($schema, $hash, '$childrenClosure', $propertiesExpr)}
        {$wrapperClosing}

        <?php if (craftile()->inPreview()) {
            craftile()->endBlock({$idVar});
        } ?>

        <?php unset($blockDataVar); ?>
        PHP;

        if ($this->componentCompiler !== null) {
            return $this->componentCompiler->compile($template);
        }

        return $template;
    }

    /**
     * Check if a node is inside a loop context (@foreach, @for, @while, @forelse).
     */
    protected function isNodeInsideLoop(AbstractNode $node, ?Document $document): bool
    {
        if (! $document) {
            return false;
        }

        $parentNodes = $document->getAllParentNodesForNode($node);

        return $parentNodes->contains(function ($parent) {
            return $parent instanceof DirectiveNode &&
                in_array($parent->content, ['foreach', 'for', 'while', 'forelse']);
        });
    }

    /**
     * Generate a dynamic ID for blocks inside loops.
     */
    protected function generateLoopId(string $baseId, AbstractNode $node, ?Document $document): string
    {
        if (! $document) {
            return $baseId;
        }

        $parentNodes = $document->getAllParentNodesForNode($node);
        $loopParent = $parentNodes->first(function ($parent) {
            return $parent instanceof DirectiveNode &&
                in_array($parent->content, ['foreach', 'for', 'while', 'forelse']);
        });

        if (! $loopParent) {
            return $baseId;
        }

        // Generate dynamic ID based on loop type
        return match ($loopParent->content) {
            'foreach', 'forelse' => "'{$baseId}-'.\$loop->index",
            'for' => "'{$baseId}-'.\$i",
            'while' => "'{$baseId}-'.uniqid()",
            default => $baseId
        };
    }
}
