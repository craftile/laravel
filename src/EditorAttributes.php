<?php

declare(strict_types=1);

namespace Craftile\Laravel;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Handles editor attributes for blocks in the Craftile editor.
 */
class EditorAttributes implements Htmlable
{
    public function __construct(
        private readonly BlockData $blockData,
        private readonly bool $inPreview = false
    ) {}

    /**
     * Get the HTML representation of the object.
     */
    public function toHtml(): string
    {
        return $this->__toString();
    }

    /**
     * Get the string representation of the object.
     */
    public function __toString(): string
    {
        if ($this->inPreview) {
            return 'data-block="'.htmlspecialchars($this->blockData->id, ENT_QUOTES, 'UTF-8').'"';
        }

        return '';
    }
}
