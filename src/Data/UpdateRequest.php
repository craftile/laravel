<?php

namespace Craftile\Laravel\Data;

class UpdateRequest
{
    /**
     * @param  array<string, array>  $blocks  Record of blocks keyed by ID
     * @param  array<array>  $regions  Array of regions
     * @param  array  $changes  Object tracking modifications
     */
    public function __construct(
        public readonly array $blocks,
        public readonly array $regions,
        public readonly array $changes,
    ) {}

    /**
     * Create UpdateRequest from array data
     */
    public static function make(array $data): self
    {
        return new self(
            $data['blocks'] ?? [],
            $data['regions'] ?? [],
            $data['changes'] ?? []
        );
    }

    /**
     * Get all blocks
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Get added block IDs
     */
    public function getAddedBlocks(): array
    {
        return $this->changes['added'] ?? [];
    }

    /**
     * Get updated block IDs
     */
    public function getUpdatedBlocks(): array
    {
        return $this->changes['updated'] ?? [];
    }

    /**
     * Get removed block IDs
     */
    public function getRemovedBlocks(): array
    {
        return $this->changes['removed'] ?? [];
    }

    /**
     * Get moved block instructions
     */
    public function getMovedBlocks(): array
    {
        return $this->changes['moved'] ?? [];
    }

    /**
     * Get all changed block IDs (added + updated + removed + moved)
     */
    public function getChangedBlocks(): array
    {
        return array_unique(array_merge(
            $this->getAddedBlocks(),
            $this->getUpdatedBlocks(),
            $this->getRemovedBlocks(),
            array_keys($this->getMovedBlocks())
        ));
    }

    /**
     * Check if there are any changes
     */
    public function hasChanges(): bool
    {
        return ! empty($this->getChangedBlocks());
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'blocks' => $this->blocks,
            'regions' => $this->regions,
            'changes' => $this->changes,
        ];
    }
}
