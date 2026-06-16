<?php

namespace Craftile\Laravel\Console;

use Craftile\Laravel\DiscoveryManifest;
use Illuminate\Console\Command;

class CacheCraftileCommand extends Command
{
    protected $signature = 'craftile:cache';

    protected $description = 'Cache the Craftile discovery manifest';

    public function handle(DiscoveryManifest $manifest): int
    {
        $cached = $manifest->cache();

        $this->components->info('Craftile discovery manifest cached successfully.');
        $this->components->twoColumnDetail('Blocks', (string) count($cached['blocks']));
        $this->components->twoColumnDetail('Presets', (string) count($cached['presets']));

        return self::SUCCESS;
    }
}
