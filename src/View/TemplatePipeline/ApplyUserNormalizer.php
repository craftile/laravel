<?php

namespace Craftile\Laravel\View\TemplatePipeline;

use Closure;
use Craftile\Laravel\Facades\Craftile;

/**
 * Apply user-defined custom template normalizer.
 * This runs on raw parsed data so users can transform anything.
 *
 * INPUT: array (raw parsed template)
 * OUTPUT: array (user-normalized template)
 */
class ApplyUserNormalizer
{
    public function handle(TemplatePayload $payload, Closure $next): mixed
    {
        $payload->data = Craftile::normalizeTemplate($payload->data, $payload->path);

        return $next($payload);
    }
}
