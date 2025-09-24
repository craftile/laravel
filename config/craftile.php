<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Preview Mode
    |--------------------------------------------------------------------------
    |
    | Configure preview mode settings for the Craftile editor integration.
    | Preview mode allows real-time editing and preview of blocks.
    |
    */
    'preview' => [
        'query_parameter' => '_preview',
        'view' => 'craftile::preview-script',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Directive Names
    |--------------------------------------------------------------------------
    |
    | Customize core block directive names by providing camelCase versions.
    | The package will automatically handle all case variants (camelCase,
    | snake_case, lowercase).
    |
    | Example: 'craftileBlock' => 'builderBlock' creates:
    | @builderBlock, @builderblock, @builder_block
    |
    */
    'directives' => [
        'craftileBlock' => 'craftileBlock',
        'craftileRegion' => 'craftileRegion',
        'craftileContent' => 'craftileContent',
        'craftileLayoutContent' => 'craftileLayoutContent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Configuration
    |--------------------------------------------------------------------------
    |
    | Configure component namespace and prefix for auto-registered components.
    |
    | 'namespace' - Changes <craftile:block/> to <custom:block/>
    | 'prefix' - Changes craftile-text-block to custom-text-block
    |
    */
    'components' => [
        'namespace' => 'craftile',
        'prefix' => 'craftile-',
    ],
];
