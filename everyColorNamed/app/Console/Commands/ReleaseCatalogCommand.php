<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ReleaseCatalogCommand extends Command
{
    protected $signature = 'colors:release
                            {buildId : Draft build ID to promote}
                            {--public-version= : Public version number (e.g. 1)}
                            {--skip-verify : Skip unique-name verification before release}';

    protected $description = 'Promote a draft build to an immutable public release version';

    public function handle(DataPaths $paths): int
    {
        $buildId = $this->argument('buildId');
        $version = (string) $this->option('public-version');

        if ($version === '') {
            $this->components->error('Pass --public-version= (e.g. --public-version=1)');

            return self::FAILURE;
        }

        if (preg_match('/^\d+$/', $version) !== 1) {
            $this->components->error('Version must be a positive integer (e.g. 1, 2)');

            return self::FAILURE;
        }

        $source = $paths->build($buildId);
        if (! is_dir($source)) {
            $this->components->error("Build not found: {$buildId}");

            return self::FAILURE;
        }

        $manifestPath = $source.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            $this->components->error("Build {$buildId} is missing manifest.json");

            return self::FAILURE;
        }

        $releasePath = $paths->release($version);
        if (is_dir($releasePath)) {
            $this->components->error("Release v{$version} already exists at {$releasePath}. Never overwrite public releases.");

            return self::FAILURE;
        }

        if (! $this->option('skip-verify')) {
            $this->components->info("Verifying unique names in {$buildId}...");
            $exitCode = Artisan::call('colors:verify-unique', ['buildId' => $buildId], $this->output);
            if ($exitCode !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->components->info("Copying {$buildId} → releases/v{$version}...");
        File::copyDirectory($source, $releasePath);

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $manifest['status'] = 'released';
        $manifest['public_version'] = $version;
        $manifest['released_at'] = now()->toIso8601String();

        file_put_contents(
            $releasePath.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        $pointer = 'releases/v'.$version;
        $paths->writeCurrentPointer($pointer);

        $this->components->info("Released v{$version} from build {$buildId}");
        $this->line('Output: '.$releasePath);
        $this->line('Current pointer: '.$pointer);

        return self::SUCCESS;
    }
}
