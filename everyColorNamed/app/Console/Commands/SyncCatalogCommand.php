<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncCatalogCommand extends Command
{
    protected $signature = 'colors:sync-catalog
                            {--disk=s3 : Filesystem disk (Laravel Cloud Object Storage)}
                            {--prefix= : Remote prefix, e.g. releases/v1}
                            {--local= : Local destination (defaults to COLOR_DATA_PATH/current root)}';

    protected $description = 'Download a released catalog from object storage into COLOR_DATA_PATH';

    public function handle(DataPaths $paths): int
    {
        $diskName = (string) $this->option('disk');
        $prefix = trim((string) $this->option('prefix'), '/');
        if ($prefix === '') {
            $this->components->error('Pass --prefix=releases/v1 (remote path containing manifest.json + browse.sqlite + shards/)');

            return self::FAILURE;
        }

        if ($this->option('local')) {
            $localRoot = (string) $this->option('local');
        } elseif (preg_match('/^releases\/v(\d+)$/', $prefix, $m) === 1) {
            $localRoot = $paths->release($m[1]);
        } else {
            $localRoot = $paths->base().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $prefix);
        }

        if (! is_dir($localRoot)) {
            mkdir($localRoot, 0755, true);
        }

        $disk = Storage::disk($diskName);
        $this->components->info("Syncing s3://…/{$prefix} → {$localRoot}");

        $required = ['manifest.json', 'browse.sqlite'];
        foreach ($required as $file) {
            $remote = $prefix.'/'.$file;
            if (! $disk->exists($remote)) {
                $this->components->error("Missing remote file: {$remote}");

                return self::FAILURE;
            }
            $this->line("  ← {$file}");
            file_put_contents($localRoot.DIRECTORY_SEPARATOR.$file, $disk->get($remote));
        }

        $shardsRemote = $prefix.'/shards';
        $shardsLocal = $localRoot.DIRECTORY_SEPARATOR.'shards';
        if (! is_dir($shardsLocal)) {
            mkdir($shardsLocal, 0755, true);
        }

        $shardFiles = array_values(array_filter(
            $disk->files($shardsRemote),
            fn (string $path): bool => str_ends_with($path, '.sqlite')
        ));

        $this->components->info(count($shardFiles).' shard files…');
        $bar = $this->output->createProgressBar(count($shardFiles));
        $bar->start();

        foreach ($shardFiles as $remotePath) {
            $name = basename($remotePath);
            $stream = $disk->readStream($remotePath);
            if ($stream === false) {
                $this->newLine();
                $this->components->error("Failed to read {$remotePath}");

                return self::FAILURE;
            }
            $out = fopen($shardsLocal.DIRECTORY_SEPARATOR.$name, 'w');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if (preg_match('/^releases\/v(\d+)$/', $prefix) === 1) {
            $paths->writeCurrentPointer($prefix);
            $this->line('Current pointer → '.$prefix);
        }

        $this->components->info('Catalog sync complete');

        return self::SUCCESS;
    }
}
