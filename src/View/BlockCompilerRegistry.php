<?php

namespace Craftile\Laravel\View;

use Craftile\Core\Data\BlockSchema;
use Craftile\Laravel\Contracts\BlockCompilerInterface;
use Craftile\Laravel\View\Compilers\DefaultBlockCompiler;

class BlockCompilerRegistry
{
    /** @var BlockCompilerInterface[] */
    private array $compilers = [];

    private DefaultBlockCompiler $defaultCompiler;

    public function __construct()
    {
        $this->defaultCompiler = new DefaultBlockCompiler;
    }

    public function register(BlockCompilerInterface $compiler): void
    {
        $this->compilers[] = $compiler;
    }

    public function findCompiler(BlockSchema $schema): BlockCompilerInterface
    {
        $compiler = collect($this->compilers)->first(fn ($compiler) => $compiler->supports($schema));

        // Return default compiler if none match
        return $compiler ?? $this->defaultCompiler;
    }

    public function getCompilers(): array
    {
        return $this->compilers;
    }
}
