<?php

declare(strict_types=1);

namespace Craftile\Laravel;

use Craftile\Laravel\Events\BlockSchemaRegistered;
use Craftile\Laravel\Support\DirectiveVariants;
use Craftile\Laravel\View\BladeDirectives;
use Craftile\Laravel\View\BlockCacheManager;
use Craftile\Laravel\View\BlockCompilerRegistry;
use Craftile\Laravel\View\Compilers\BladeComponentBlockCompiler;
use Craftile\Laravel\View\CraftileTagsCompiler;
use Craftile\Laravel\View\JsonViewCompiler;
use Craftile\Laravel\View\NodeTransformerRegistry;
use Craftile\Laravel\View\NodeTransformers\CraftileBlockDirectiveTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileBlockTagTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileChildrenTagTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileContentTransformer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\CompilerEngine;

class CraftileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/craftile.php', 'craftile');

        $this->app->singleton('craftile', Craftile::class);
        $this->app->singleton(BlockSchemaRegistry::class);
        $this->app->singleton(PropertyTransformerRegistry::class);
        $this->app->singleton(BlockDiscovery::class);
        $this->app->singleton(BlockFlattener::class);
        $this->app->singleton(BlockDatastore::class);
        $this->app->singleton(PreviewDataCollector::class);

        $this->registerBlockCompilerRegistry();
        $this->registerBladeNodeTransformers();
        $this->registerJsonViewCompiler();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'craftile');

        $this->bootBladeExtensions();
        $this->bootRegisterBladeComponentBlocks();
    }

    protected function registerBlockCompilerRegistry()
    {
        $this->app->singleton(BlockCompilerRegistry::class, function ($app) {
            $registry = new BlockCompilerRegistry;

            $registry->register(new BladeComponentBlockCompiler);

            return $registry;
        });
    }

    protected function registerBladeNodeTransformers()
    {
        $this->app->singleton(NodeTransformerRegistry::class, function ($app) {
            $registry = new NodeTransformerRegistry;

            $registry->register(new CraftileBlockTagTransformer);
            $registry->register(new CraftileBlockDirectiveTransformer);
            $registry->register(new CraftileChildrenTagTransformer);
            $registry->register(new CraftileContentTransformer);

            return $registry;
        });
    }

    /**
     * Register JSON/YAML view compiler.
     */
    protected function registerJsonViewCompiler()
    {
        $this->app->singleton(BlockCacheManager::class, function ($app) {
            return new BlockCacheManager($app['files']);
        });

        $this->app->singleton(JsonViewCompiler::class, function ($app) {
            return new JsonViewCompiler(
                $app['files'],
                $app['config']['view.compiled'],
                $app['blade.compiler'],
                $app[BlockCacheManager::class]
            );
        });

        $this->app->singleton('jsonview.compiler', function ($app) {
            return $app[JsonViewCompiler::class];
        });

        $this->app->extend('view.engine.resolver', function ($resolver) {
            $resolver->register('jsonview', function () {
                return new CompilerEngine($this->app['jsonview.compiler'], $this->app['files']);
            });

            return $resolver;
        });

        // Register view extensions
        $extensions = config('craftile.view_compiler.extensions', ['json', 'yml', 'yaml']);
        foreach ($extensions as $extension) {
            $this->app['view']->addExtension($extension, 'jsonview');
        }
    }

    protected function bootBladeExtensions()
    {
        Blade::directive('children', [BladeDirectives::class, 'children']);

        // Register Blade precompiler for craftile tags
        Blade::precompiler(app(CraftileTagsCompiler::class));

        $directives = config('craftile.directives', []);
        $coreDirectives = [
            'craftileRegion' => [BladeDirectives::class, 'region'],
        ];

        foreach ($coreDirectives as $original => $handler) {
            $customName = $directives[$original] ?? $original;

            // Register all case variants for flexibility
            foreach (DirectiveVariants::generate($customName) as $variant) {
                if ($variant !== $customName) {
                    Blade::directive($variant, $handler);
                }
            }
        }
    }

    protected function bootRegisterBladeComponentBlocks()
    {
        Event::listen(BlockSchemaRegistered::class, function (BlockSchemaRegistered $event) {
            if ($event->schema->class && is_subclass_of($event->schema->class, \Illuminate\View\Component::class)) {
                $componentName = 'craftile-'.$event->schema->slug;
                Blade::component($componentName, $event->schema->class);
            }
        });
    }
}
