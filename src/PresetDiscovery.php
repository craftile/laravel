<?php

namespace Craftile\Laravel;

use Craftile\Core\Contracts\BlockInterface;
use Craftile\Core\Data\BlockPreset;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PresetDiscovery
{
    public function __construct(
        protected BlockSchemaRegistry $registry
    ) {}

    /**
     * Scan a directory for preset classes and register them.
     *
     * @param  string  $namespace  The namespace prefix for classes in this directory
     * @param  string  $directory  The directory path to scan
     */
    public function scan(string $namespace, string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = Finder::create()
            ->files()
            ->in($directory)
            ->name('*.php');

        foreach ($files as $file) {
            $class = $this->classFromFile($file, $namespace);

            if (! $class || ! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, BlockPreset::class)) {
                continue;
            }

            $type = $class::getType();

            if ($type === null) {
                continue;
            }

            $blockType = $this->resolveBlockType($type);

            if ($blockType === null) {
                continue;
            }

            $this->registry->registerPreset($blockType, $class);
        }
    }

    /**
     * Extract the class name from the given file path.
     */
    protected function classFromFile(SplFileInfo $file, string $namespace): ?string
    {
        $relativePath = str_replace('.php', '', $file->getRelativePathname());

        return $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
    }

    /**
     * Resolve block type from getType() return value.
     * Supports both type strings and block class names.
     *
     * @param  string  $type  Block type string or block class name
     * @return string|null Resolved block type or null if invalid
     */
    protected function resolveBlockType(string $type): ?string
    {
        if (class_exists($type) && is_subclass_of($type, BlockInterface::class)) {
            return $type::type();
        }

        return $type;
    }
}
