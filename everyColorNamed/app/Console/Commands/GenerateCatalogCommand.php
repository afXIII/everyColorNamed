<?php

namespace App\Console\Commands;

use App\Services\Color\CatalogGenerator;
use App\Services\Color\DataPaths;
use App\Services\Color\WordBanks;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GenerateCatalogCommand extends Command
{
    protected $signature = 'colors:generate-catalog
                            {--level=0 : Catalog level 0-10}
                            {--workers=1 : Parallel worker processes (R-sharded)}
                            {--build-id= : Existing build id (worker mode)}
                            {--worker= : Worker index 0..workers-1}
                            {--skip-finalize : Only generate shards (worker mode)}';

    protected $description = 'Generate a versioned color catalog build with sharded SQLite files';

    public function handle(
        DataPaths $paths,
        WordBanks $wordBanks,
        CatalogGenerator $generator,
    ): int {
        $level = (int) $this->option('level');
        $workers = max(1, (int) $this->option('workers'));
        $worker = $this->option('worker');
        $buildId = $this->option('build-id') ?: null;
        $skipFinalize = (bool) $this->option('skip-finalize');

        if (! isset(config('color.catalog_levels')[$level])) {
            $this->components->error("Invalid catalog level: {$level}");

            return self::FAILURE;
        }

        if (! is_file($paths->mergedSeeds())) {
            $this->components->error('Missing merged seeds. Run colors:import-seeds first.');

            return self::FAILURE;
        }

        if (! is_file($paths->uniqueLexicon())) {
            $this->components->warn('Missing unique_lexicon.json — unique triple fallback will use word banks only.');
        }

        $wordBanks->loadFromFile($paths->wordBanks());
        $wordBanks->loadLexicon($paths->uniqueLexicon());

        // Parent orchestrator for multi-worker builds
        if ($workers > 1 && $worker === null) {
            return $this->runParallel($level, $workers, $paths, $generator);
        }

        $options = [
            'skip_finalize' => $skipFinalize || ($workers > 1 && $worker !== null),
        ];
        if ($buildId) {
            $options['build_id'] = $buildId;
        }
        if ($worker !== null) {
            $options['worker_index'] = (int) $worker;
            $options['worker_count'] = $workers;
        }

        $this->components->info("Generating catalog level {$level}".($worker !== null ? " (worker {$worker}/{$workers})" : '').'...');

        $buildId = $generator->generate($level, $paths->mergedSeeds(), $paths->wordBanks(), $this->output, $options);

        $this->components->info("Build complete: {$buildId}");
        $this->line('Output: '.$paths->build($buildId));

        return self::SUCCESS;
    }

    private function runParallel(int $level, int $workers, DataPaths $paths, CatalogGenerator $generator): int
    {
        $buildId = now()->format('Ymd-His').'-'.substr(sha1((string) microtime(true)), 0, 4);
        $paths->ensureDirectory('builds', $buildId, 'registry');
        $paths->ensureDirectory('builds', $buildId, 'shards');

        $this->components->info("Parallel level {$level} with {$workers} workers → {$buildId}");

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        /** @var list<Process> $processes */
        $processes = [];

        for ($i = 0; $i < $workers; $i++) {
            $process = new Process([
                $php,
                '-d', 'memory_limit=8G',
                $artisan,
                'colors:generate-catalog',
                '--level='.$level,
                '--workers='.$workers,
                '--worker='.$i,
                '--build-id='.$buildId,
                '--skip-finalize',
            ], base_path());
            $process->setTimeout(null);
            $process->start();
            $processes[] = $process;
            $this->line("Started worker {$i}");
        }

        $failed = false;
        while ($processes !== []) {
            foreach ($processes as $index => $process) {
                if ($process->isRunning()) {
                    continue;
                }

                $out = trim($process->getIncrementalOutput().$process->getIncrementalErrorOutput());
                if ($out !== '') {
                    $this->line($out);
                }

                if (! $process->isSuccessful()) {
                    $this->components->error("Worker failed:\n".$process->getErrorOutput().$process->getOutput());
                    $failed = true;
                } else {
                    $this->components->info('Worker finished');
                }

                unset($processes[$index]);
            }

            foreach ($processes as $process) {
                $chunk = $process->getIncrementalOutput();
                if ($chunk !== '') {
                    $this->output->write($chunk);
                }
            }

            if ($processes !== []) {
                usleep(200_000);
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $total = match (true) {
            $level === 10 => 16_777_216,
            default => (int) (json_decode(
                (string) file_get_contents($paths->build($buildId).'/build_progress_w0.json'),
                true
            )['total'] ?? 0) * $workers,
        };

        // Sum exact totals from worker progress files
        $total = 0;
        $collisions = 0;
        foreach (glob($paths->build($buildId).'/build_progress_w*.json') ?: [] as $file) {
            $payload = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $total += (int) ($payload['processed'] ?? 0);
            $collisions += (int) ($payload['collisions'] ?? 0);
        }

        $this->components->info('All workers done — finalizing browse index…');
        $generator->finalizeBuild($buildId, $level, $total, $collisions, $this->output);
        $this->components->info("Build complete: {$buildId}");
        $this->line('Output: '.$paths->build($buildId));

        return self::SUCCESS;
    }
}
