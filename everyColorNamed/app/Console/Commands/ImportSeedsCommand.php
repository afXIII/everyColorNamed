<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use App\Services\Color\SeedHexIndex;
use App\Services\Color\SeedImporter;
use App\Services\Color\SeedMerger;
use Illuminate\Console\Command;

class ImportSeedsCommand extends Command
{
    protected $signature = 'colors:import-seeds';

    protected $description = 'Import open color name datasets into merged seeds';

    public function handle(DataPaths $paths, SeedImporter $importer, SeedMerger $merger, SeedHexIndex $seedHexIndex): int
    {
        $this->components->info('Downloading and importing color seed datasets...');

        $paths->ensureDirectory('seeds');
        $rows = $importer->import();

        $this->components->info('Imported '.number_format(count($rows)).' raw rows. Merging...');

        $result = $merger->merge($rows);

        $mergedPath = $paths->mergedSeeds();
        $this->components->info('Wrote '.number_format(count($result['seeds'])).' merged seeds to '.$mergedPath);

        $this->components->info('Building hex seed index...');
        $seedHexIndex->buildFromMerged($mergedPath);
        $this->components->info('Wrote '.$seedHexIndex->indexPath());

        return self::SUCCESS;
    }
}
