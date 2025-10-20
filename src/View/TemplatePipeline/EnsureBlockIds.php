<?php

namespace Craftile\Laravel\View\TemplatePipeline;

use Closure;

/**
 * Ensure root blocks have an 'id' field.
 * - If blocks are keyed by ID but missing 'id' field, use array key as ID
 * - Convert numeric arrays to associative (keyed by block ID)
 *
 * INPUT: array (template with possibly inconsistent block format)
 * OUTPUT: array (template with normalized block IDs)
 */
class EnsureBlockIds
{
    public function handle(array $data, Closure $next): mixed
    {
        if (! isset($data['blocks']) || empty($data['blocks'])) {
            return $next($data);
        }

        $blocks = $data['blocks'];
        $normalized = [];

        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (! isset($block['id'])) {
                if (is_string($key)) {
                    $block['id'] = $key;
                } else {
                    continue;
                }
            }

            $blockId = $block['id'];
            $normalized[$blockId] = $block;
        }

        $data['blocks'] = $normalized;

        return $next($data);
    }
}
