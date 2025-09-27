<?php

namespace Craftile\Laravel\Middlewares;

use Closure;
use Craftile\Laravel\PreviewDataCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreviewScriptMiddleware
{
    protected PreviewDataCollector $previewCollector;

    public function __construct(PreviewDataCollector $previewCollector)
    {
        $this->previewCollector = $previewCollector;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app('craftile')->inPreview()) {
            $this->injectPreviewScripts($response, $request);
        }

        return $response;
    }

    /**
     * Inject preview client and page data scripts into the response.
     */
    protected function injectPreviewScripts(Response $response, Request $request): void
    {
        // Only inject into HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (! $content || ! str_contains($content, '</body>')) {
            return;
        }

        $pageData = $this->getCurrentPageData();

        $scripts = $this->buildPreviewScripts($pageData);

        $content = str_replace('</body>', $scripts.'</body>', $content);
        $response->setContent($content);
    }

    /**
     * Get current page data from the collected data during rendering.
     */
    protected function getCurrentPageData(): array
    {
        return $this->previewCollector->getCollectedData();
    }

    /**
     * Build the scripts to inject for preview functionality.
     */
    protected function buildPreviewScripts(array $pageData): string
    {
        $pageDataJson = json_encode($pageData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return view(config('craftile.preview.view'), ['pageDataJson' => $pageDataJson])->render();
    }
}
