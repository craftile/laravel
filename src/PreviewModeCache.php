<?php

namespace Craftile\Laravel;

/**
 * Holds the preview mode answer for the current request.
 *
 * Bound as scoped, so long-lived workers (Octane, queue) compute it again on every request.
 */
class PreviewModeCache
{
    protected ?bool $value = null;

    public function get(): ?bool
    {
        return $this->value;
    }

    public function put(bool $value): bool
    {
        return $this->value = $value;
    }

    public function forget(): void
    {
        $this->value = null;
    }
}
