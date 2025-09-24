# Craftile Laravel

> **Laravel integration for the Craftile visual editor** - Build dynamic, block-based layouts with seamless editor integration.

[![Latest Version](https://img.shields.io/packagist/v/craftile/laravel?style=flat-square)](https://packagist.org/packages/craftile/laravel)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10%2B-red?style=flat-square)](https://laravel.com)

This package provides Laravel integration for the [Craftile Visual Editor](https://github.com/craftile/editor), enabling you to create block-based content management systems with Blade templates.

## 🚀 Installation

Install via Composer:

```bash
composer require craftile/laravel
```

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Craftile\Laravel\CraftileServiceProvider"
```

This will create a `config/craftile.php` file where you can customize:

- Blade directive names
- Component namespace
- Block compiler settings
- Cache configuration

## 📦 Creating Blocks

### 1. Block Class

Create a block by implementing the `BlockInterface`:

```php
<?php

namespace App\Blocks;

use Craftile\Core\Contracts\BlockInterface;
use Craftile\Core\Data\Property\Text;
use Craftile\Core\Data\Property\Boolean;

class Hero implements BlockInterface
{
    public static function getType(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero Section';
    }

    public static function properties(): array
    {
        return [
            Text::make('title', 'Title')->default('Welcome'),
            Text::make('subtitle', 'Subtitle'),
            Boolean::make('showButton', 'Show Button')->default(true),
        ];
    }

    protected static array $accepts = ['button', 'text']; // Optional: specify accepted child types

    public function render(): mixed
    {
        return view('blocks.hero');
    }
}
```

### 2. Block Template

Create the corresponding Blade template:

```blade
<!-- resources/views/blocks/hero.blade.php -->
<section class="hero bg-gray-900 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold mb-4">{{ $block->properties->title }}</h1>

        @if($block->properties->subtitle)
            <p class="text-xl mb-8">{{ $block->properties->subtitle }}</p>
        @endif

        @if($block->properties->showButton)
            @children
        @endif
    </div>
</section>
```

### 3. Register the Block

Register your block in a service provider:

```php
// In AppServiceProvider or dedicated BlockServiceProvider
use Craftile\Laravel\Facades\Craftile;

public function boot()
{
    Craftile::registerBlock(Hero::class);
}
```

## 🎯 Using Blocks in Templates

### Blade Directives

Use blocks directly in your Blade templates:

```blade
{{-- Basic block usage --}}
@craftileBlock('hero', 'hero-1', ['title' => 'Hello World'])

```

### Component Tags

Or use the component syntax:

```blade
<craftile:block type="hero" id="hero-1" :properties="['title' => 'Hello World']" />
```

### JSON/YAML Templates

Create template files that can be rendered directly:

```json
// resources/views/pages/homepage.json
{
    "blocks": {
        "hero-1": {
            "id": "hero-1",
            "type": "hero",
            "properties": {
                "title": "Welcome to our site",
                "subtitle": "Build amazing things with Craftile"
            },
            "children": ["btn-1"]
        },
        "btn-1": {
            "id": "btn-1",
            "type": "button",
            "properties": {
                "text": "Get Started",
                "variant": "primary"
            }
        }
    },
    "regions": [
        {
            "name": "main",
            "blocks": ["hero-1"]
        }
    ]
}
```

Then render in your route or controller:

```php
Route::get('/', function () {
    return view('pages.homepage'); // Will automatically compile the JSON template
});
```

## 🔧 Property Types

Craftile provides several property types for your blocks:

```php
use Craftile\Core\Data\Property\{Text, Textarea, Boolean, Select};

public static function properties(): array
{
    return [
        Text::make('title', 'Title')
            ->default('Default title')
            ->required(),

        Textarea::make('content', 'Content')
            ->default('Enter your content here...'),

        Boolean::make('isVisible', 'Visible')
            ->default(true),

        Select::make('size', 'Size')
            ->options([
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
                ['value' => 'lg', 'label' => 'Large'],
            ])
            ->default('md'),
    ];
}
```

## 🎨 Working with Children

Blocks can accept and render child blocks:

```php
// In your block class
protected static array $accepts = ['*']; // Accept any child type
// or
protected static array $accepts = ['button', 'text']; // Accept specific types

// In your block template
@children {{-- Renders all child blocks --}}
```

## 🔧 Advanced Configuration

### Custom Directive Names

Customize directive names in `config/craftile.php`:

```php
'directives' => [
    'craftileBlock' => 'block',        // @block instead of @craftileBlock
    'craftileContent' => 'content',    // @content instead of @craftileContent
],
```

### Component Namespace

Change the component tag namespace:

```php
'components' => [
    'namespace' => 'builder', // <builder:block> instead of <craftile:block>
],
```