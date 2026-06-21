<?php

namespace Craftile\Laravel\View\TemplatePipeline;

class TemplatePayload
{
    public function __construct(
        public readonly string $path,
        public array $data = [],
    ) {}
}
