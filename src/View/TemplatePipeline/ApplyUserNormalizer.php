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
    public function handle(array $data, Closure $next): mixed
    {
        // Apply user's custom normalizer (if registered)
        $data = Craftile::normalizeTemplate($data);

        return $next($data);
    }
}
