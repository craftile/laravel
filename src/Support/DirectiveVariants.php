<?php

declare(strict_types=1);

namespace Craftile\Laravel\Support;

use Illuminate\Support\Str;

class DirectiveVariants
{
    /**
     * Generate directive name variants for a given camelCase string.
     *
     * @return string[]
     */
    public static function generate(string $camelCase): array
    {
        $snakeCase = Str::snake($camelCase);

        $variants = [
            $camelCase,                                    // craftileBlock
            ucfirst($camelCase),                          // CraftileBlock
            $snakeCase,                                   // craftile_block
        ];

        if (str_contains($snakeCase, '_')) {
            $variants[] = strtolower(str_replace('_', '', $snakeCase)); // craftileblock
        }

        return array_unique($variants);
    }

    /**
     * Generate end directive variants (prefixed with 'end').
     *
     * @return string[]
     */
    public static function generateEnd(string $camelCase): array
    {
        $variants = self::generate($camelCase);

        return array_map(function ($variant) {
            if (ctype_upper($variant[0])) {
                return 'End'.$variant; // PascalCase -> EndPascalCase
            } elseif (str_contains($variant, '_')) {
                return 'end_'.$variant; // snake_case -> end_snake_case
            } elseif (ctype_lower($variant)) {
                return 'end'.$variant; // all lowercase -> endlowercase
            } else {
                return 'end'.ucfirst($variant); // camelCase -> endCamelCase
            }
        }, $variants);
    }
}
