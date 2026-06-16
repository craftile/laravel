<?php

namespace Craftile\Laravel;

use Craftile\Core\Contracts\BlockInterface;
use Craftile\Core\Data\BlockPreset;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DiscoveryManifest
{
    public function __construct(
        protected Application $app,
        protected Filesystem $files,
        protected DiscoveryRoots $roots
    ) {}

    public function path(): string
    {
        return $this->app->bootstrapPath('cache/craftile.php');
    }

    public function exists(): bool
    {
        return $this->files->exists($this->path());
    }

    public function get(): array
    {
        return $this->exists()
            ? $this->load()
            : $this->build();
    }

    public function load(): array
    {
        return require $this->path();
    }

    public function build(): array
    {
        $blockRoots = $this->resolveRoots($this->roots->blocks(), 'blocks');
        $presetRoots = $this->resolveRoots($this->roots->presets(), 'presets');

        return [
            'generated_at' => now('UTC')->toIso8601String(),
            'roots' => [
                'blocks' => $this->displayRoots($blockRoots),
                'presets' => $this->displayRoots($presetRoots),
            ],
            'blocks' => $this->discover($blockRoots, BlockInterface::class),
            'presets' => $this->discover($presetRoots, BlockPreset::class),
        ];
    }

    public function cache(): array
    {
        $manifest = $this->build();
        $path = $this->path();

        $this->files->ensureDirectoryExists(dirname($path));

        $contents = "<?php\n\nreturn ".var_export($manifest, true).";\n";
        $tmp = $path.'.tmp';

        $this->files->put($tmp, $contents);
        $this->files->move($tmp, $path);

        return $manifest;
    }

    public function clear(): bool
    {
        if (! $this->exists()) {
            return false;
        }

        return $this->files->delete($this->path());
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $roots
     * @return array<int, array{namespace: string, path: string}>
     */
    protected function resolveRoots(array $roots, string $type): array
    {
        $resolved = [];

        foreach ($roots as $root) {
            if (! $this->files->isDirectory($root['path'])) {
                continue;
            }

            $realPath = realpath($root['path']);

            if ($realPath === false) {
                continue;
            }

            $resolved[] = [
                'namespace' => $root['namespace'],
                'path' => $realPath,
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $roots
     * @return array<int, array{namespace: string, path: string}>
     */
    protected function displayRoots(array $roots): array
    {
        return array_map(fn (array $root) => [
            'namespace' => $root['namespace'],
            'path' => $this->displayPath($root['path']),
        ], $roots);
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $roots
     * @return array<int, array{class: string, path: string, namespace: string}>
     */
    protected function discover(array $roots, string $baseClass): array
    {
        $entries = [];

        foreach ($roots as $root) {
            $files = iterator_to_array(
                Finder::create()
                    ->files()
                    ->ignoreDotFiles(true)
                    ->ignoreVCS(true)
                    ->in($root['path'])
                    ->name('*.php'),
                false
            );

            usort($files, fn (SplFileInfo $a, SplFileInfo $b) => $a->getRelativePathname() <=> $b->getRelativePathname());

            foreach ($files as $file) {
                $class = $this->classFromFile($file, $root['namespace']);

                if (! class_exists($class) || ! is_subclass_of($class, $baseClass)) {
                    continue;
                }

                $entries[] = [
                    'class' => $class,
                    'path' => $this->displayPath($file->getRealPath()),
                    'namespace' => $root['namespace'],
                ];
            }
        }

        return $entries;
    }

    protected function classFromFile(SplFileInfo $file, string $namespace): string
    {
        $relativePath = preg_replace('/\.php$/', '', $file->getRelativePathname());

        return $namespace.'\\'.str_replace(['/', '\\'], '\\', $relativePath);
    }

    protected function displayPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $basePath = rtrim(str_replace('\\', '/', $this->app->basePath()), '/');

        if ($path === $basePath) {
            return '';
        }

        if (str_starts_with($path, $basePath.'/')) {
            return substr($path, strlen($basePath) + 1);
        }

        return $path;
    }
}
