<?php

namespace Craftile\Laravel\View\NodeTransformers\Concerns;

use Stillat\BladeParser\Nodes\LiteralNode;

trait HandlesLiterals
{
    /**
     * Create a LiteralNode with the given content.
     */
    protected function createLiteralNode(string $content): LiteralNode
    {
        $newNode = new LiteralNode();
        $newNode->setContent($content);

        return $newNode;
    }

    /**
     * If $expr is a quoted string ('...' or "..."), return its inner text (with simple unescaping).
     * Otherwise return null.
     */
    protected function unquoteLiteral(string $expr): ?string
    {
        $expr = trim($expr);

        if ($expr === '') {
            return null;
        }

        $first = $expr[0];
        $last = substr($expr, -1);

        if (($first === "'" || $first === '"') && $last === $first) {
            $value = substr($expr, 1, -1);
            // minimal unescape for the matching quote and backslash
            $value = str_replace(["\\{$first}", '\\\\'], [$first, '\\'], $value);

            return $value;
        }

        return null;
    }
}
