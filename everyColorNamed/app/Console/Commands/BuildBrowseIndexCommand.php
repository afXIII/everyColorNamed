<?php

namespace App\Console\Commands;

use App\Services\Color\BrowseIndexWriter;
use App\Services\Color\CatalogGenerator;
use App\Services\Color\DataPaths;
use Illuminate\Console\Command;

class BuildBrowseIndexCommand extends Command
{
    protected $signature = 'colors:build-browse-index {buildId? : Build ID (defaults to data/current)}';

    protected $description = 'Build or rebuild browse.sqlite for an existing catalog build';

    public function handle(DataPaths $paths, BrowseIndexWriter $writer, CatalogGenerator $generator): int
    {
        $buildId = $this->argument('buildId') ?? $paths->currentPointer();
        $buildPath = str_starts_with($buildId, 'releases/')
            ? $paths->catalogRoot($buildId)
            : $paths->build($buildId);
        $shardsPath = $buildPath.DIRECTORY_SEPARATOR.'shards';
        $browsePath = $buildPath.DIRECTORY_SEPARATOR.'browse.sqlite';

        abort_unless(is_dir($shardsPath), 1, "Shards not found for build {$buildId}");

        $this->components->info("Building browse index for {$buildId}...");
        $writer->build($shardsPath, $browsePath);

        $manifestPath = $buildPath.DIRECTORY_SEPARATOR.'manifest.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $manifest['jump_nav'] = $generator->buildJumpNav($browsePath);
            $manifest['nav_order'] = config('color.nav_order');
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
        }

        $this->components->info("Wrote {$browsePath}");

        return self::SUCCESS;
    }
}
