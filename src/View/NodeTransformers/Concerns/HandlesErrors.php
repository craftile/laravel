<?php

namespace Craftile\Laravel\View\NodeTransformers\Concerns;

use Illuminate\View\ViewException;
use Stillat\BladeParser\Nodes\AbstractNode;

trait HandlesErrors
{
    /**
     * Throw a ViewException with context information.
     */
    protected function throwError(string $message, AbstractNode $node): never
    {
        throw new ViewException(
            message: $message,
            code: 0,
            severity: 1,
            filename: app('blade.compiler')->getPath(),
            line: $node->position->startLine
        );
    }
}
