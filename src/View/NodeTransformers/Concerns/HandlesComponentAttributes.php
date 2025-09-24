<?php

namespace Craftile\Laravel\View\NodeTransformers\Concerns;

use Stillat\BladeParser\Nodes\Components\ComponentNode;

trait HandlesComponentAttributes
{
    /**
     * Extract attribute value from component node.
     */
    protected function extractComponentAttributeValue(ComponentNode $tag, string $attributeName): string
    {
        $attribute = collect($tag->parameters)->firstWhere(fn ($param) => $param->materializedName === $attributeName);

        if (! $attribute) {
            $this->throwError("<craftile:block> requires {$attributeName} attribute", $tag);
        }

        if (! $this->isValidIdentifier($attribute->value)) {
            $this->throwError("<craftile:block> attribute {$attributeName} value must be a literal string, not an expression", $tag);
        }

        return $attribute->value;
    }

    /**
     * Extract properties expression from component attributes.
     */
    protected function extractComponentPropertiesExpr(ComponentNode $tag): string
    {
        $attributes = collect($tag->parameters)->filter(function ($attr) {
            return ! in_array($attr->materializedName, ['type', 'id']);
        });

        if ($attributes->isEmpty()) {
            return '[]';
        }

        $properties = $attributes->map(function ($attr) {
            return sprintf(
                "'%s' => %s",
                $attr->materializedName,
                $this->isBladeLiteral($attr->value) ? $attr->valueNode->content : $attr->value
            );
        })->join(',');

        return '['.$properties.']';
    }

    /**
     * Check if string is a valid identifier.
     */
    protected function isValidIdentifier(string $string): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $string);
    }

    /**
     * Check if string is a Blade literal (not containing variables or expressions).
     */
    protected function isBladeLiteral(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        // Reject PHP variables and concatenation: $var, $var1.$var2, $obj->prop
        if (preg_match('/\$\w+/', $string)) {
            return false;
        }

        // Reject Blade interpolation syntax
        if (preg_match('/\{\{.*?\}\}|\{\{\{.*?\}\}\}|\{\!\!.*!\!\}/', $string)) {
            return false;
        }

        // Reject Blade directives
        if (preg_match('/@\w+/', $string)) {
            return false;
        }

        // Reject standalone function calls
        if (preg_match('/^\s*\w+\s*\([^)]*\)\s*;?\s*$/', $string)) {
            return false;
        }

        return true;
    }
}
