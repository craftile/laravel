<?php

return [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            // Add your custom block paths here
            // Example: app_path('Blocks')
        ],
        'namespaces' => [
            // Namespace mappings for discovery
            // Example: 'App\\Blocks' => app_path('Blocks')
        ],
    ],

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
    | Region View Prefix
    |--------------------------------------------------------------------------
    |
    | Configure the prefix used when resolving region views.
    | Default: 'regions' (results in 'regions.{regionName}')
    |
    */
    'region_view_prefix' => 'regions',

    /*
    |--------------------------------------------------------------------------
    | Component Configuration
    |--------------------------------------------------------------------------
    |
    | Configure custom component namespace
    |
    | 'namespace' - Changes <craftile:block/> to <custom:block/>
    |
    */
    'components' => [
        'namespace' => 'craftile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for block data parsing.
    | TTL is in seconds (3600 = 1 hour).
    |
    */
    'cache' => [
        'ttl' => 3600, // Cache TTL in seconds (default: 1 hour)
    ],

    /*
    |--------------------------------------------------------------------------
    | BlockData Class
    |--------------------------------------------------------------------------
    |
    | Configure the default BlockData class used for block instances.
    | Must extend Craftile\Laravel\BlockData.
    |
    */
    'block_data_class' => \Craftile\Laravel\BlockData::class,

    /*
    |--------------------------------------------------------------------------
    | BlockSchema Class
    |--------------------------------------------------------------------------
    |
    | Configure the default BlockSchema class used for block schema definitions.
    | Must extend Craftile\Core\Data\BlockSchema.
    |
    */
    'block_schema_class' => \Craftile\Core\Data\BlockSchema::class,
];
