<?php

namespace Craftile\Laravel;

/**
 * @phpstan-consistent-constructor
 */
abstract class Property
{
    public function __construct(
        public string $id,
        public string $label,
        public array $meta = []
    ) {}

    public static function make(string $id, string $label): static
    {
        return new static($id, $label);
    }

    public function default(mixed $value): static
    {
        $this->meta['default'] = $value;

        return $this;
    }

    public function placeholder(string $value): static
    {
        $this->meta['placeholder'] = $value;

        return $this;
    }

    public function info(string $value): static
    {
        $this->meta['info'] = $value;

        return $this;
    }

    abstract public function type(): string;

    public function getId(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return array_merge([
            'id' => $this->id,
            'type' => $this->type(),
            'label' => $this->label,
        ], $this->meta);
    }
}
