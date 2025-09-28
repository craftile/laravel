<?php

namespace Craftile\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

class JsonViewLoaded
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $path) {}
}
