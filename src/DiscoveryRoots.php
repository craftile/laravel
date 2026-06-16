<?php

namespace Craftile\Laravel;

use Craftile\Laravel\Exceptions\InvalidDiscoveryRootException;
use Illuminate\Contracts\Foundation\Application;

class DiscoveryRoots
{
    /** @var array<int, array{namespace: string, path: string}> */
    protected array $blockRoots = [];

    /** @var array<int, array{namespace: string, path: string}> */
    protected array $presetRoots = [];

    public function __construct(
        protected Application $app
    ) {}

    public function addBlockRoot(string $namespace, string $path): void
    {
        $this->addRoot($this->blockRoots, $namespace, $path);
    }

    public function addPresetRoot(string $namespace, string $path): void
    {
        $this->addRoot($this->presetRoots, $namespace, $path);
    }

    /**
     * @return array<int, array{namespace: string, path: string}>
     */
    public function blocks(): array
    {
        return $this->roots('blocks', $this->blockRoots);
    }

    /**
     * @return array<int, array{namespace: string, path: string}>
     */
    public function presets(): array
    {
        return $this->roots('presets', $this->presetRoots);
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $declaredRoots
     * @return array<int, array{namespace: string, path: string}>
     */
    protected function roots(string $key, array $declaredRoots): array
    {
        $configRoots = config('craftile.discovery.enabled', true)
            ? $this->normalizeConfigRoots($key, config("craftile.discovery.{$key}", []))
            : [];

        return $this->dedupe(array_merge($configRoots, $declaredRoots));
    }

    /**
     * @param  array<int|string, mixed>  $roots
     * @return array<int, array{namespace: string, path: string}>
     */
    protected function normalizeConfigRoots(string $key, array $roots): array
    {
        $normalized = [];

        foreach ($roots as $namespace => $path) {
            if (is_array($path)) {
                if (! isset($path['namespace'], $path['path'])) {
                    throw new InvalidDiscoveryRootException("Craftile discovery {$key} entries must include [namespace] and [path].");
                }

                $namespace = $path['namespace'];
                $path = $path['path'];
            }

            if (! is_string($namespace) || ! is_string($path)) {
                throw new InvalidDiscoveryRootException("Craftile discovery {$key} entries must map a namespace to a path.");
            }

            $normalized[] = $this->normalizeRoot($namespace, $path);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $roots
     */
    protected function addRoot(array &$roots, string $namespace, string $path): void
    {
        $root = $this->normalizeRoot($namespace, $path);

        foreach ($roots as $existing) {
            if ($existing === $root) {
                return;
            }
        }

        $roots[] = $root;
    }

    /**
     * @return array{namespace: string, path: string}
     */
    protected function normalizeRoot(string $namespace, string $path): array
    {
        $namespace = trim($namespace, '\\');

        if ($namespace === '') {
            throw new InvalidDiscoveryRootException('Craftile discovery namespace cannot be empty.');
        }

        return [
            'namespace' => $namespace,
            'path' => $this->normalizePath($path),
        ];
    }

    protected function normalizePath(string $path): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR.'/\\');

        if ($path === '') {
            throw new InvalidDiscoveryRootException('Craftile discovery path cannot be empty.');
        }

        if (! $this->isAbsolutePath($path)) {
            $path = $this->app->basePath($path);
        }

        return $path;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    /**
     * @param  array<int, array{namespace: string, path: string}>  $roots
     * @return array<int, array{namespace: string, path: string}>
     */
    protected function dedupe(array $roots): array
    {
        $deduped = [];
        $seen = [];

        foreach ($roots as $root) {
            $key = $root['namespace'].'|'.$root['path'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $root;
        }

        return $deduped;
    }
}
