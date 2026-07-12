<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use App\Services\Color\SeedHexIndex;
use Illuminate\Console\Command;

class BuildSeedIndexCommand extends Command
{
    protected $signature = 'colors:build-seed-index';

    protected $description = 'Build the SQLite hex lookup index from merged.json';

    public function handle(DataPaths $paths, SeedHexIndex $seedHexIndex): int
    {
        $mergedPath = $paths->mergedSeeds();

        if (! is_file($mergedPath)) {
            $this->components->error('Missing merged seeds. Run colors:import-seeds first.');

            return self::FAILURE;
        }

        $this->components->info('Building hex seed index...');
        $seedHexIndex->buildFromMerged($mergedPath);
        $this->components->info('Wrote '.$seedHexIndex->indexPath());

        return self::SUCCESS;
    }
}
