<?php

declare(strict_types=1);

namespace Craftile\Laravel;

use Craftile\Laravel\Console\CacheCraftileCommand;
use Craftile\Laravel\Console\ClearCraftileCommand;
use Craftile\Laravel\Events\BlockSchemaRegistered;
use Craftile\Laravel\PropertyTransformers\DynamicSourceTransformer;
use Craftile\Laravel\View\BlockCacheManager;
use Craftile\Laravel\View\BlockCompilerRegistry;
use Craftile\Laravel\View\Compilers\BladeComponentBlockCompiler;
use Craftile\Laravel\View\CraftileTagsCompiler;
use Craftile\Laravel\View\JsonViewCompiler;
use Craftile\Laravel\View\JsonViewParser;
use Craftile\Laravel\View\NodeTransformerRegistry;
use Craftile\Laravel\View\NodeTransformers\CraftileBlockDirectiveTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileBlockTagTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileChildrenTagTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileContentTransformer;
use Craftile\Laravel\View\NodeTransformers\CraftileRegionDirectiveTransformer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Component;
use Illuminate\View\Engines\CompilerEngine;

class CraftileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/craftile.php', 'craftile');

        $this->app->singleton('craftile', Craftile::class);
        $this->app->singleton(BlockSchemaRegistry::class);
        $this->app->singleton(PropertyTransformerRegistry::class);
        $this->app->singleton(DiscoveryRoots::class);
        $this->app->singleton(DiscoveryManifest::class);
        $this->app->singleton(BlockFlattener::class);
        $this->app->singleton(BlockDatastore::class);
        $this->app->singleton(PreviewDataCollector::class);

        $this->registerBlockCompilerRegistry();
        $this->registerBladeNodeTransformers();
        $this->registerJsonViewCompiler();
        $this->registerPropertyTransformers();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'craftile');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/craftile.php' => config_path('craftile.php'),
            ], 'craftile-config');

            $this->commands([
                CacheCraftileCommand::class,
                ClearCraftileCommand::class,
            ]);

            $this->optimizes(
                optimize: 'craftile:cache',
                clear: 'craftile:clear',
            );
        }

        $this->bootRegisterBladeComponentBlocks();

        $this->app->booted(function () {
            $this->bootBladeExtensions();
            $this->bootViewExtensions();
        });
    }

    protected function registerBlockCompilerRegistry()
    {
        $this->app->singleton(BlockCompilerRegistry::class, function () {
            $registry = new BlockCompilerRegistry;

            $registry->register(new BladeComponentBlockCompiler);

            return $registry;
        });
    }

    protected function registerBladeNodeTransformers()
    {
        $this->app->singleton(NodeTransformerRegistry::class, function () {
            $registry = new NodeTransformerRegistry;

            $registry->register(new CraftileBlockTagTransformer);
            $registry->register(new CraftileBlockDirectiveTransformer);
            $registry->register(new CraftileChildrenTagTransformer);
            $registry->register(new CraftileContentTransformer);
            $registry->register(new CraftileRegionDirectiveTransformer);

            return $registry;
        });
    }

    /**
     * Register JSON/YAML view compiler.
     */
    protected function registerJsonViewCompiler()
    {
        $this->app->singleton(JsonViewParser::class);

        $this->app->singleton(BlockCacheManager::class, function ($app) {
            return new BlockCacheManager($app['files']);
        });

        $this->app->singleton(JsonViewCompiler::class, function ($app) {
            return new JsonViewCompiler(
                $app['files'],
                $app['config']['view.compiled'],
                $app['blade.compiler'],
                $app[BlockCacheManager::class],
                $app[JsonViewParser::class]
            );
        });

        $this->app->singleton('jsonview.compiler', function ($app) {
            return $app[JsonViewCompiler::class];
        });

        $this->app->extend('view.engine.resolver', function ($resolver) {
            $resolver->register('jsonview', function () {
                return new CompilerEngine($this->app->get('jsonview.compiler'), $this->app->get('files'));
            });

            return $resolver;
        });
    }

    protected function bootBladeExtensions()
    {
        // Register Blade precompiler for craftile tags
        Blade::precompiler(app(CraftileTagsCompiler::class));
    }

    protected function bootViewExtensions()
    {
        $extensions = array_merge(
            ['json', 'yml', 'yaml'],
            config('craftile.php_template_extensions', ['craft.php'])
        );

        $view = $this->app->get('view');

        foreach ($extensions as $extension) {
            $view->addExtension($extension, 'jsonview');
        }
    }

    protected function bootRegisterBladeComponentBlocks()
    {
        Event::listen(BlockSchemaRegistered::class, function (BlockSchemaRegistered $event) {
            if ($event->schema->class && is_subclass_of($event->schema->class, Component::class)) {
                $componentName = 'craftile-'.$event->schema->slug;
                Blade::component($componentName, $event->schema->class);
            }
        });
    }

    protected function registerPropertyTransformers()
    {
        $this->app->afterResolving(PropertyTransformerRegistry::class, function ($registry) {
            $registry->register('__dynamic_source__', new DynamicSourceTransformer);
        });
    }
}
