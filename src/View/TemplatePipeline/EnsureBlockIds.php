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
        $typeCounters = [];

        foreach ($blocks as $key => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (! isset($block['id'])) {
                if (is_string($key)) {
                    $block['id'] = $key;
                } else {
                    $type = $block['type'] ?? 'block';
                    $sanitizedType = $this->sanitizeTypeForId($type);

                    if (! isset($typeCounters[$type])) {
                        $typeCounters[$type] = 0;
                    }

                    $counter = $typeCounters[$type]++;
                    $hash = substr(hash('xxh128', $type.'_'.$counter.'_'.json_encode($block)), 0, 8);
                    $block['id'] = "{$sanitizedType}_{$counter}_{$hash}";
                }
            }

            $blockId = $block['id'];
            $normalized[$blockId] = $block;
        }

        $data['blocks'] = $normalized;

        return $next($data);
    }

    /**
     * Sanitize block type for use in ID.
     * Converts types like "@visual/some-block" to "visual_some_block"
     */
    private function sanitizeTypeForId(string $type): string
    {
        // Remove @ prefix
        $sanitized = ltrim($type, '@');

        // Replace / and - with _
        $sanitized = str_replace(['/', '-'], '_', $sanitized);

        // Convert to snake_case
        $sanitized = strtolower($sanitized);

        return $sanitized ?: 'block';
    }
}
