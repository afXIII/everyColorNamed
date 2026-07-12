<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use App\Services\Color\SeedHexIndex;
use App\Services\Color\SeedMerger;
use Illuminate\Console\Command;

class RemergeSeedsCommand extends Command
{
    protected $signature = 'colors:remerge-seeds';

    protected $description = 'Re-merge raw seed rows into merged.json (applies current English-only filters)';

    public function handle(DataPaths $paths, SeedMerger $merger, SeedHexIndex $seedHexIndex): int
    {
        $rawPath = $paths->joinRawRows();

        if (! is_file($rawPath)) {
            $this->components->error('Missing raw rows. Run colors:import-seeds first.');

            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents($rawPath), true, flags: JSON_THROW_ON_ERROR);
        $this->components->info('Re-merging '.number_format(count($rows)).' raw rows...');

        $result = $merger->merge($rows);
        $this->components->info('Wrote '.number_format(count($result['seeds'])).' merged seeds.');

        $this->components->info('Rebuilding hex seed index...');
        $seedHexIndex->buildFromMerged($paths->mergedSeeds());

        return self::SUCCESS;
    }
}
