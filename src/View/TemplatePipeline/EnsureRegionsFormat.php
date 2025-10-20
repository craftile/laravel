<?php

namespace Craftile\Laravel\View\TemplatePipeline;

use Closure;

/**
 * Ensure template has regions format.
 * Converts simple block list to regions structure if needed.
 *
 * INPUT: array (template with flat blocks)
 * OUTPUT: array (template with regions structure)
 */
class EnsureRegionsFormat
{
    public function handle(array $data, Closure $next): mixed
    {
        if (isset($data['regions'])) {
            return $next($data);
        }

        $blocks = $data['blocks'] ?? [];
        $regionName = $data['name'] ?? 'main';

        $blockOrder = match (true) {
            isset($data['order']) => $data['order'],
            ! empty($blocks) => array_values(array_map(fn ($block) => $block['id'], $blocks)),
            default => []
        };

        $data['regions'] = [
            [
                'name' => $regionName,
                'blocks' => $blockOrder,
            ],
        ];

        return $next($data);
    }
}
