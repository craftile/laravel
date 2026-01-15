<?php

namespace Craftile\Laravel\View;

/**
 * Utility class for wrapping block content with HTML generated from Emmet syntax.
 * Automatically injects data-block attribute into the root tag.
 *
 * This class is used at COMPILE TIME to generate the wrapper HTML that will be
 * directly embedded in the compiled template.
 */
class WrapperCompiler
{
    /**
     * Compile wrapper HTML with data-block attribute and content placeholder.
     *
     * This method is called at COMPILE TIME to generate the wrapper HTML.
     * The resulting HTML will be directly embedded in the compiled PHP template.
     *
     * @param  string  $emmet  The Emmet-like syntax for the wrapper
     * @param  string  $blockId  The block ID to inject as data-block attribute
     * @return array [$opening, $closing] HTML parts split by content location
     */
    public static function compileWrapper(string $emmet, string $blockId): array
    {
        if (! str_contains($emmet, '__content__')) {
            $emmet .= '{__content__}';
        }

        $html = SimpleEmmetParser::parse($emmet);

        // Inject data-block attribute into the first tag
        $html = preg_replace(
            '/^(<\w+)(\s|>)/',
            '$1 data-block="'.htmlspecialchars($blockId, ENT_QUOTES).'"$2',
            $html,
            1
        );

        [$opening, $closing] = explode('__content__', $html, 2);

        return [$opening, $closing];
    }
}
