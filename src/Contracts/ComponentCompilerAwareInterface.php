<?php

declare(strict_types=1);

namespace Craftile\Laravel\Contracts;

use Illuminate\View\Compilers\ComponentTagCompiler;

interface ComponentCompilerAwareInterface
{
    /**
     * Set the component compiler instance.
     */
    public function setComponentCompiler(ComponentTagCompiler $componentCompiler): void;
}