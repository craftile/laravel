<?php

namespace Craftile\Laravel\View\TemplatePipeline;

use Closure;
use Craftile\Laravel\Exceptions\JsonViewException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parse template file from path.
 * Handles JSON, YAML, and PHP (.craft.php) files.
 *
 * INPUT: string (file path)
 * OUTPUT: array (parsed template data)
 */
class ParseTemplateFile
{
    protected array $phpTemplateExtensions;

    public function __construct()
    {
        $this->phpTemplateExtensions = config('craftile.php_template_extensions', ['craft.php']);
    }

    public function handle(string $path, Closure $next): mixed
    {
        if (! file_exists($path)) {
            throw new JsonViewException("Template file not found: {$path}", $path);
        }

        // Check if this is a PHP template
        if ($this->isPhpTemplate($path)) {
            $data = $this->evaluatePhpTemplate($path);

            return $next($data);
        }

        // Parse JSON/YAML
        $content = file_get_contents($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $data = match ($extension) {
            'json' => $this->parseJson($content, $path),
            'yml', 'yaml' => $this->parseYaml($content, $path),
            default => throw new JsonViewException("Unsupported template format: {$extension}", $path)
        };

        return $next($data);
    }

    /**
     * Check if the given path is a PHP template file.
     */
    protected function isPhpTemplate(string $path): bool
    {
        foreach ($this->phpTemplateExtensions as $ext) {
            if (str_ends_with($path, '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a PHP template file and return the template data.
     *
     * @throws JsonViewException
     */
    protected function evaluatePhpTemplate(string $path): array
    {
        ob_start();

        try {
            $result = require $path;
            ob_end_clean();

            if (is_array($result)) {
                return $result;
            }

            if ($result instanceof \Craftile\Core\Data\Template) {
                return $result();
            }

            throw new JsonViewException(
                "PHP template must return an array or Template instance: {$path}",
                $path
            );
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new JsonViewException(
                "PHP template evaluation failed: {$path}. {$e->getMessage()}",
                $path,
                0,
                $e
            );
        }
    }

    /**
     * Parse JSON content.
     *
     * @throws JsonViewException
     */
    protected function parseJson(string $content, string $path): array
    {
        try {
            return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonViewException(
                "JSON parsing failed: {$path}. {$e->getMessage()}",
                $path,
                0,
                $e
            );
        }
    }

    /**
     * Parse YAML content.
     *
     * @throws JsonViewException
     */
    protected function parseYaml(string $content, string $path): array
    {
        try {
            return Yaml::parse($content);
        } catch (\Exception $e) {
            throw new JsonViewException(
                "YAML parsing failed: {$path}. {$e->getMessage()}",
                $path,
                0,
                $e
            );
        }
    }
}
