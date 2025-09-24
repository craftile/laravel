<?php

namespace Craftile\Laravel\Events;

use Craftile\Core\Data\BlockSchema;
use Illuminate\Foundation\Events\Dispatchable;

class BlockSchemaRegistered
{
    use Dispatchable;

    public function __construct(public readonly BlockSchema $schema) {}
}
