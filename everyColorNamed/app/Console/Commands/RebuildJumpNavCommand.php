<?php

namespace App\Console\Commands;

use App\Services\Color\CatalogGenerator;
use App\Services\Color\DataPaths;
use Illuminate\Console\Command;
use PDO;

class RebuildJumpNavCommand extends Command
{
    protected $signature = 'colors:rebuild-jump-nav {buildId? : Build ID or releases/vN (defaults to data/current)}';

    protected $description = 'Recompute jump_nav offsets (middle of each hue bucket) into manifest.json';

    public function handle(DataPaths $paths, CatalogGenerator $generator): int
    {
        $buildId = $this->argument('buildId');

        try {
            $buildPath = $buildId === null
                ? $paths->catalogRoot()
                : (str_starts_with((string) $buildId, 'releases/')
                    ? $paths->catalogRoot((string) $buildId)
                    : $paths->build((string) $buildId));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $browsePath = $buildPath.DIRECTORY_SEPARATOR.'browse.sqlite';
        $manifestPath = $buildPath.DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($browsePath) || ! is_file($manifestPath)) {
            $this->components->error("Missing browse.sqlite or manifest.json in {$buildPath}");

            return self::FAILURE;
        }

        $pdo = new PDO('sqlite:'.$browsePath);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_browse_hex ON browse_rows(hex)');

        $jumpNav = $generator->buildJumpNav($browsePath);
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $manifest['jump_nav'] = $jumpNav;
        $manifest['nav_order'] = config('color.nav_order');
        unset($manifest['jump_targets']);

        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        foreach ($jumpNav as $bucket => $entry) {
            $hex = $entry['hex'] ?? '?';
            $this->line(sprintf('  %-8s → #%s  offset=%s', $bucket, $hex, number_format($entry['offset'])));
        }

        $this->components->info('Updated jump_nav in '.$manifestPath);

        return self::SUCCESS;
    }
}
