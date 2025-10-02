<?php

namespace Craftile\Laravel\View;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\BlockFlattener;
use Craftile\Laravel\BlockSchemaRegistry;
use Craftile\Laravel\Contracts\BlockCompilerInterface;
use Craftile\Laravel\Exceptions\JsonViewException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\Compiler;
use Illuminate\View\Compilers\CompilerInterface;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class JsonViewCompiler extends Compiler implements CompilerInterface
{
    public const EXTENSIONS = ['json', 'yml', 'yaml'];

    private BladeCompiler $blade;

    private BlockCacheManager $cacheManager;

    /**
     * Create a new compiler instance.
     *
     * @param  string  $cachePath
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Filesystem $files, $cachePath, BladeCompiler $blade, BlockCacheManager $cacheManager)
    {
        parent::__construct($files, $cachePath);

        $this->blade = $blade;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Compile the view at the given path.
     *
     * @param  string|null  $path
     */
    public function compile($path = null): void
    {
        if (is_null($path) || is_null($this->cachePath)) {
            return;
        }

        $compiled = $this->compileJsonFile($path);

        $compiled = $this->appendFilePath($compiled, $path);

        $this->ensureCompiledDirectoryExists(
            $compiledPath = $this->getCompiledPath($path)
        );

        $this->files->put($compiledPath, $compiled);
    }

    public function compileJsonFile(string $path): string
    {
        try {
            $templateContent = $this->files->get($path);
            $template = $this->parseTemplate($templateContent, $path);
        } catch (JsonException $e) {
            throw new JsonViewException("JSON parsing failed in template: {$path}. {$e->getMessage()}", $path, 0, $e);
        } catch (ParseException $e) {
            throw new JsonViewException("YAML parsing failed in template: {$path}. {$e->getMessage()}", $path, 0, $e);
        } catch (Throwable $e) {
            throw new JsonViewException("Failed to read template file: {$path}. {$e->getMessage()}", $path, 0, $e);
        }

        try {
            $compiledTemplate = $this->compileTemplate($template, $path);

            return <<<PHP
            <?php \\Craftile\\Laravel\\Facades\\BlockDatastore::loadFile("$path"); ?>
            <?php \\Craftile\\Laravel\\Events\\JsonViewLoaded::dispatch("$path"); ?>
            $compiledTemplate
            PHP;
        } catch (JsonViewException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new JsonViewException("Template compilation failed: {$path}. {$e->getMessage()}", $path, 0, $e);
        }
    }

    /**
     * Compile template data into PHP code.
     */
    public function compileTemplate(array $templateData, string $path = ''): string
    {
        $template = $this->normalizeTemplate($templateData);

        if (empty($template['regions'])) {
            return "<?php // Empty template ?>\n";
        }

        $this->invalidateStaleBlockCaches($template);

        $staticBlocksChildren = $this->collectStaticBlockChildren($template, $path);
        $staticBlocksMapCode = $this->generateStaticBlocksMapCode($staticBlocksChildren);

        $regionsCodes = [];
        foreach ($template['regions'] as $region) {
            $regionName = $region['name'] ?? 'unnamed';

            $blocks = '';
            foreach ($region['blocks'] as $blockId) {
                if (! isset($template['blocks'][$blockId])) {
                    throw new JsonViewException("Block '{$blockId}' referenced in template but not defined in blocks section", $path);
                }
                $blocks .= $this->compileBlockSelectively($template['blocks'][$blockId], $template, $path);
            }

            $regionCode = <<<PHP
            <?php if (craftile()->inPreview()) {
                craftile()->startRegion("{$regionName}");
                echo '<!--BEGIN region: {$regionName}-->';
            } ?>
            {$blocks}
            <?php if (craftile()->inPreview()) {
                echo '<!--END region: {$regionName}-->';
                craftile()->endRegion("{$regionName}");
            } ?>
            PHP;

            $regionsCodes[] = $regionCode;
        }
        $regionsCode = implode('', $regionsCodes);

        return $staticBlocksMapCode."\n".$regionsCode;
    }

    /**
     * Invalidate caches for blocks that have changed and all their ancestors.
     */
    protected function invalidateStaleBlockCaches(array $template): void
    {
        $changedBlocks = $this->findChangedBlocks($template);
        $blocksToInvalidate = $this->findBlocksAndAncestors($changedBlocks, $template);

        foreach ($blocksToInvalidate as $blockId) {
            // Flush all cache versions for this block (solves rollback issue)
            $this->cacheManager->flushBlock($blockId);
        }
    }

    /**
     * Find all blocks that have changed (cache miss).
     */
    protected function findChangedBlocks(array $template): array
    {
        $changedBlocks = [];

        foreach ($template['blocks'] as $blockData) {
            $cacheKey = $this->cacheManager->getCacheKey($blockData);
            if (! $this->cacheManager->exists($cacheKey)) {
                $changedBlocks[] = $blockData['id'];
            }
        }

        return $changedBlocks;
    }

    /**
     * Find all blocks and their ancestors that need recompilation.
     */
    protected function findBlocksAndAncestors(array $changedBlocks, array $template): array
    {
        $blocksToInvalidate = [];

        foreach ($changedBlocks as $blockId) {
            // Add the changed block itself
            $blocksToInvalidate[] = $blockId;

            // Follow parent chain to add all ancestors
            $currentBlockId = $blockId;
            while (isset($template['blocks'][$currentBlockId]['parentId']) && $template['blocks'][$currentBlockId]['parentId']) {
                $parentId = $template['blocks'][$currentBlockId]['parentId'];
                $blocksToInvalidate[] = $parentId;
                $currentBlockId = $parentId;
            }
        }

        return array_unique($blocksToInvalidate);
    }

    /**
     * Collect children closures for static blocks.
     */
    protected function collectStaticBlockChildren(array $template, string $path): array
    {
        $staticBlocksChildren = [];

        foreach ($template['blocks'] as $blockId => $blockData) {
            if (($blockData['static'] ?? false) && ! empty($blockData['children'])) {
                // Generate children closure for this static block
                $childrenClosureCode = $this->generateChildrenClosureForStaticBlock($blockData, $template, $path);
                if ($childrenClosureCode) {
                    $staticBlocksChildren[$blockId] = $childrenClosureCode;
                }
            }
        }

        return $staticBlocksChildren;
    }

    /**
     * Generate children closure code for a static block.
     */
    protected function generateChildrenClosureForStaticBlock(array $blockData, array $template, string $path): string
    {
        $childrenCode = '';
        foreach ($blockData['children'] as $childId) {
            if (! isset($template['blocks'][$childId])) {
                throw new JsonViewException("Child block '{$childId}' referenced by static block '{$blockData['id']}' but not defined in blocks section", $path);
            }
            $childData = $template['blocks'][$childId];
            $childrenCode .= $this->compileBlockSelectively($childData, $template, $path);
        }

        if (! $childrenCode) {
            return '';
        }

        return "function() use (\$__env) {
            ob_start();
            ?>{$childrenCode}<?php
            \$result = ob_get_clean();
            return \$result;
        }";
    }

    /**
     * Generate PHP code for the static blocks children map.
     */
    protected function generateStaticBlocksMapCode(array $staticBlocksChildren): string
    {
        if (empty($staticBlocksChildren)) {
            return '';
        }

        $mapEntries = [];
        foreach ($staticBlocksChildren as $blockId => $closureCode) {
            $mapEntries[] = "    '{$blockId}' => {$closureCode}";
        }

        $mapCode = implode(",\n", $mapEntries);

        return "<?php\n\$__staticBlocksChildren = [\n{$mapCode}\n];\n?>";
    }

    /**
     * Compile a block selectively using hash-based caching.
     */
    protected function compileBlockSelectively($blockData, $template, $path): string
    {
        $cacheKey = $this->cacheManager->getCacheKey($blockData);

        if ($this->cacheManager->exists($cacheKey)) {
            return $this->cacheManager->get($cacheKey);
        }

        $bladeTemplate = $this->compileBlock($blockData, $template, $path);

        $fullyCompiledBlock = $this->blade->compileString($bladeTemplate);

        $this->cacheManager->put($cacheKey, $fullyCompiledBlock);

        return $fullyCompiledBlock;
    }

    protected function compileBlock($blockData, $template, $path): string
    {
        $hash = hash('xxh128', $blockData['id']);
        $blockDataVar = '$__blockData'.$hash;

        return <<<PHP
        <?php $blockDataVar = \\Craftile\\Laravel\\Facades\\BlockDatastore::getBlock("{$blockData['id']}"); ?>

        <?php if (craftile()->shouldRenderBlock($blockDataVar)): ?>

        <?php if (craftile()->inPreview()) {
            craftile()->startBlock("{$blockData['id']}", $blockDataVar);
        } ?>


        {$this->generateCodeForRenderingBlock($blockData, $hash, $template, $path)}

        <?php if (craftile()->inPreview()) {
            craftile()->endBlock("{$blockData['id']}");
        } ?>

        <?php endif; ?>

        <?php unset($blockDataVar); ?>
        PHP;
    }

    protected function generateCodeForRenderingBlock(array $blockData, string $hash, array $template, string $path): string
    {
        try {
            $blockType = $blockData['type'] ?? 'unknown';
            $blockId = $blockData['id'] ?? 'unknown';

            $schema = app(BlockSchemaRegistry::class)->get($blockType);
            if (! $schema) {
                throw new JsonViewException("Unknown block type '{$blockType}' for block '{$blockId}'", $path);
            }

            $compiler = $this->findBlockCompiler($schema);

            // Generate closure code for children if block has them
            $childrenClosureCode = '';
            if (! empty($blockData['children'])) {
                $childrenCode = '';
                foreach ($blockData['children'] as $childId) {
                    if (! isset($template['blocks'][$childId])) {
                        throw new JsonViewException("Child block '{$childId}' referenced by block '{$blockId}' but not defined in blocks section", $path);
                    }
                    $childData = $template['blocks'][$childId];

                    // Skip static children from parent's closure (they will be handled by <craftile:block/> directives)
                    if ($childData['static'] ?? false) {
                        continue;
                    }

                    $childrenCode .= $this->compileBlockSelectively($childData, $template, $path);
                }

                if ($childrenCode) {
                    $childrenClosureCode = "function() use (\$__env) {
                        ob_start();
                        ?>{$childrenCode}<?php
                        \$result = ob_get_clean();
                        // Clean up any temporary variables
                        return \$result;
                    }";
                }
            }

            $compiledBlock = $compiler->compile($schema, $hash, $childrenClosureCode);

            if (! empty($childrenClosureCode)) {
                $closureVar = '$__children'.$hash;
                $compiledBlock .= "\n<?php unset({$closureVar}); ?>";
            }

            return $compiledBlock;
        } catch (JsonViewException $e) {
            throw $e;
        } catch (Throwable $e) {
            $blockId = $blockData['id'] ?? 'unknown';
            $blockType = $blockData['type'] ?? 'unknown';
            throw new JsonViewException("Block compilation failed for '{$blockId}' (type: {$blockType}): {$e->getMessage()}", $path, 0, $e);
        }
    }

    protected function findBlockCompiler(BlockSchema $schema): BlockCompilerInterface
    {
        $registry = app(BlockCompilerRegistry::class);

        return $registry->findCompiler($schema);
    }

    /**
     * Normalize template to default format.
     */
    protected function normalizeTemplate(array $templateData): array
    {
        $normalized = $this->normalizeTemplateFormat($templateData);

        $flattener = app(BlockFlattener::class);
        if ($flattener->hasNestedStructure($normalized)) {
            $flattened = $flattener->flattenNestedStructure($normalized);
            unset($flattened['_idMappings']);

            return $flattened;
        }

        return $normalized;
    }

    /**
     * Normalize template format to standard structure.
     */
    protected function normalizeTemplateFormat(array $templateData): array
    {
        if (isset($templateData['regions'])) {
            return $templateData;
        }

        $blocks = $templateData['blocks'] ?? [];

        // Determine region name and block order based on template format
        $regionName = match (true) {
            isset($templateData['name']) => $templateData['name'],
            default => 'main'
        };

        $blockOrder = match (true) {
            isset($templateData['order']) => $templateData['order'],
            ! empty($blocks) => array_keys($blocks),
            default => []
        };

        if (empty($blocks) && empty($blockOrder) && ! isset($templateData['name']) && ! isset($templateData['order'])) {
            return ['blocks' => [], 'regions' => []];
        }

        return [
            'blocks' => $blocks,
            'regions' => [
                [
                    'name' => $regionName,
                    'blocks' => $blockOrder,
                ],
            ],
        ];
    }

    /**
     * Append the file path to the compiled string.
     *
     * @param  string  $contents
     * @return string
     */
    protected function appendFilePath($contents, $path)
    {
        $tokens = $this->getOpenAndClosingPhpTokens($contents);

        if (! empty($tokens) && end($tokens) !== T_CLOSE_TAG) {
            $contents .= ' ?>';
        }

        return $contents."<?php /**PATH {$path} ENDPATH**/ ?>";
    }

    /**
     * Parse template content based on file extension (JSON or YAML).
     */
    private function parseTemplate(string $content, string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'json':
                return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            case 'yml':
            case 'yaml':
                return Yaml::parse($content);
            default:
                throw new JsonViewException("Unsupported template format: {$extension}", $path);
        }
    }

    /**
     * Get the open and closing PHP tag tokens from the given string.
     *
     * @param  string  $contents
     * @return array
     */
    protected function getOpenAndClosingPhpTokens($contents)
    {
        $tokens = token_get_all($contents);
        $filteredTokens = [];

        foreach ($tokens as $token) {
            $tokenType = is_array($token) ? $token[0] : $token;
            if (in_array($tokenType, [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG])) {
                $filteredTokens[] = $tokenType;
            }
        }

        return $filteredTokens;
    }
}
