<?php

namespace Craftile\Laravel\Console;

use Craftile\Laravel\DiscoveryManifest;
use Illuminate\Console\Command;

class ClearCraftileCommand extends Command
{
    protected $signature = 'craftile:clear';

    protected $description = 'Clear the Craftile discovery manifest';

    public function handle(DiscoveryManifest $manifest): int
    {
        $manifest->clear();

        $this->components->info('Craftile discovery manifest cleared successfully.');

        return self::SUCCESS;
    }
}
