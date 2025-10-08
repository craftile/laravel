<?php

namespace Craftile\Laravel;

use Craftile\Core\Contracts\BlockInterface;
use Craftile\Laravel\Facades\Craftile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BlockDiscovery
{
    public function __construct(
        protected BlockSchemaRegistry $registry
    ) {}

    /**
     * Scan a directory for block classes and register them with the BlockSchemaRegistry.
     *
     * @param  string  $namespace  The namespace prefix for classes in this directory
     * @param  string  $directory  The directory path to scan
     */
    public function scan(string $namespace, string $directory): void
    {
        if (! is_dir($directory)) {
            return; // Gracefully handle missing directories
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Get the relative path from the base directory
            $relativePath = str_replace($directory.DIRECTORY_SEPARATOR, '', $file->getPathname());
            // Remove .php extension
            $relativePath = str_replace('.php', '', $relativePath);
            // Convert path separators to namespace separators
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            // Build the full class name
            $class = $namespace.'\\'.$relativePath;

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, BlockInterface::class)) {
                continue;
            }

            $schemaClass = Craftile::getBlockSchemaClass();
            $schema = $schemaClass::fromClass($class);
            $this->registry->register($schema);
        }
    }
}
