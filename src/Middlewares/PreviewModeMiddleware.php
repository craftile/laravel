<?php

namespace Craftile\Laravel\Middlewares;

use Closure;
use Craftile\Laravel\PreviewDataCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreviewModeMiddleware
{
    private PreviewDataCollector $previewCollector;

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
    private function injectPreviewScripts(Response $response, Request $request): void
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

        // Get current page data from collected data during rendering
        $pageData = $this->getCurrentPageData();

        // Build the scripts to inject
        $scripts = $this->buildPreviewScripts($pageData);

        // Inject scripts before closing body tag
        $content = str_replace('</body>', $scripts.'</body>', $content);
        $response->setContent($content);
    }

    /**
     * Get current page data from the collected data during rendering.
     */
    private function getCurrentPageData(): array
    {
        // Use collected preview data from rendering - page info is set during template compilation
        return $this->previewCollector->getCollectedData();
    }

    /**
     * Build the scripts to inject for preview functionality.
     */
    private function buildPreviewScripts(array $pageData): string
    {
        $pageDataJson = json_encode($pageData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return view(config('craftile.preview.view'), ['pageDataJson' => $pageDataJson])->render();
    }
}
