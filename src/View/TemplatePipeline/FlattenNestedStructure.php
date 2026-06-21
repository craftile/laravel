<?php

namespace Craftile\Laravel\View\TemplatePipeline;

use Closure;
use Craftile\Laravel\BlockFlattener;

/**
 * Flatten nested block structures into flat format.
 * Converts parent/children hierarchy to flat blocks array with references.
 *
 * INPUT: array (template with possibly nested structure)
 * OUTPUT: array (template with flat structure + regions)
 */
class FlattenNestedStructure
{
    public function __construct(
        protected BlockFlattener $flattener
    ) {}

    public function handle(TemplatePayload $payload, Closure $next): mixed
    {
        if ($this->flattener->hasNestedStructure($payload->data)) {
            $payload->data = $this->flattener->flattenNestedStructure($payload->data);
            unset($payload->data['_idMappings']);
        }

        return $next($payload);
    }
}
