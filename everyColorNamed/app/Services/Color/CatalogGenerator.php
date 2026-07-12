<?php

namespace App\Services\Color;

use Illuminate\Console\OutputStyle;

class CatalogGenerator
{
    public function __construct(
        private readonly DataPaths $paths,
        private readonly SeedIndex $seedIndex,
        private readonly WordBanks $wordBanks,
    ) {}

    /**
     * @param  array{
     *     build_id?: string,
     *     worker_index?: int,
     *     worker_count?: int,
     *     skip_finalize?: bool
     * }  $options
     */
    public function generate(
        int $level,
        string $mergedSeedsPath,
        string $wordBanksPath,
        ?OutputStyle $output = null,
        array $options = [],
    ): string {
        $this->seedIndex->loadFromFile($mergedSeedsPath);
        $this->wordBanks->loadFromFile($wordBanksPath);
        $this->wordBanks->loadLexicon($this->paths->uniqueLexicon());

        $workerIndex = $options['worker_index'] ?? null;
        $workerCount = max(1, (int) ($options['worker_count'] ?? 1));
        $skipFinalize = (bool) ($options['skip_finalize'] ?? false);

        $buildId = $options['build_id'] ?? now()->format('Ymd-His').'-'.substr(sha1((string) microtime(true)), 0, 4);
        $buildPath = $this->paths->ensureDirectory('builds', $buildId);
        $registrySuffix = $workerIndex === null ? 'used_names.sqlite' : "used_names_w{$workerIndex}.sqlite";
        $registryPath = $buildPath.DIRECTORY_SEPARATOR.'registry'.DIRECTORY_SEPARATOR.$registrySuffix;
        $shardsPath = $buildPath.DIRECTORY_SEPARATOR.'shards';
        $progressPath = $buildPath.DIRECTORY_SEPARATOR.(
            $workerIndex === null ? 'build_progress.json' : "build_progress_w{$workerIndex}.json"
        );

        $this->paths->ensureDirectory('builds', $buildId, 'registry');
        $this->paths->ensureDirectory('builds', $buildId, 'shards');

        $registry = new UsedNamesRegistry($registryPath);
        $this->reserveSeedNames($registry);

        $naming = new ColorNamingService(
            $this->seedIndex,
            $this->wordBanks,
            $registry,
            computeNearest: $level < 8,
            workerIndex: $workerIndex,
            workerCount: $workerCount,
        );
        $writer = new ShardWriter($shardsPath);

        $total = $this->countForLevel($level, $workerIndex, $workerCount);
        $processed = 0;
        $startedAt = microtime(true);
        $progressEvery = $total > 1_000_000 ? 25_000 : ($total > 100_000 ? 5_000 : 250);

        $this->writeProgress($progressPath, [
            'build_id' => $buildId,
            'status' => 'running',
            'catalog_level' => $level,
            'worker_index' => $workerIndex,
            'processed' => 0,
            'total' => $total,
            'started_at' => now()->toIso8601String(),
        ]);

        if ($output !== null) {
            $workerLabel = $workerIndex === null ? '' : " worker {$workerIndex}/{$workerCount}";
            $output->writeln("<fg=cyan>Level {$level}{$workerLabel}</> — ".number_format($total).' colors → '.$buildPath);
        }

        foreach ($this->colorsForLevel($level, $workerIndex, $workerCount) as $triplet) {
            [$r, $g, $b] = $triplet;
            $colorId = ColorId::fromRgb($r, $g, $b);
            $hex = ColorId::toHex($colorId);
            $lab = ColorMath::rgbToLab($r, $g, $b);
            $hueBucket = ColorMath::hueBucket($r, $g, $b);

            $named = $naming->nameFor($r, $g, $b, $colorId);

            $writer->insert([
                'color_id' => $colorId,
                'r' => $r,
                'g' => $g,
                'b' => $b,
                'hex' => $hex,
                'name' => $named['name'],
                'hue_bucket' => $hueBucket,
                'text_contrast' => ColorMath::textContrast($r, $g, $b),
                'nearest_seed_hex' => $named['nearest_seed_hex'],
                'nearest_seed_name' => $named['nearest_seed_name'],
                'delta_e' => $named['delta_e'],
                'l' => $lab[0],
                'a' => $lab[1],
                'b_lab' => $lab[2],
            ]);

            $processed++;

            if ($processed % $progressEvery === 0 || $processed === $total) {
                $elapsed = microtime(true) - $startedAt;
                $rate = $processed / max($elapsed, 0.001);
                $remaining = ($total - $processed) / max($rate, 0.001);
                $percent = round(($processed / max($total, 1)) * 100, 2);

                if ($output !== null) {
                    $eta = $remaining >= 3600
                        ? sprintf('%dh %02dm', intdiv((int) $remaining, 3600), intdiv(((int) $remaining) % 3600, 60))
                        : gmdate('i:s', (int) $remaining);
                    $output->write("\r<fg=cyan>Generating</> ".number_format($processed).'/'.number_format($total)." ({$percent}%) ".number_format($rate, 0).'/s collisions='.number_format($registry->collisionCount())." ETA {$eta}   ");
                }

                $this->writeProgress($progressPath, [
                    'build_id' => $buildId,
                    'status' => 'running',
                    'catalog_level' => $level,
                    'worker_index' => $workerIndex,
                    'processed' => $processed,
                    'total' => $total,
                    'rate_per_sec' => round($rate, 1),
                    'collisions' => $registry->collisionCount(),
                    'eta_seconds' => (int) $remaining,
                    'updated_at' => now()->toIso8601String(),
                ]);
            }
        }

        if ($output !== null) {
            $output->writeln('');
            $output->writeln('Finalizing shards + registry…');
        }

        $writer->finalize();
        $registry->finalize();

        $this->writeProgress($progressPath, [
            'build_id' => $buildId,
            'status' => 'complete',
            'catalog_level' => $level,
            'worker_index' => $workerIndex,
            'processed' => $processed,
            'total' => $total,
            'collisions' => $registry->collisionCount(),
            'completed_at' => now()->toIso8601String(),
        ]);

        if (! $skipFinalize) {
            $this->finalizeBuild($buildId, $level, $total, $registry->collisionCount(), $output);
        }

        return $buildId;
    }

    public function finalizeBuild(
        string $buildId,
        int $level,
        int $total,
        int $collisions = 0,
        ?OutputStyle $output = null,
    ): void {
        $buildPath = $this->paths->build($buildId);
        $shardsPath = $buildPath.DIRECTORY_SEPARATOR.'shards';

        if ($output !== null) {
            $output->writeln('Building browse index (this can take a while)…');
        }

        $browseIndexPath = $buildPath.DIRECTORY_SEPARATOR.'browse.sqlite';
        (new BrowseIndexWriter)->build($shardsPath, $browseIndexPath);

        $namingReport = [
            'build_id' => $buildId,
            'catalog_level' => $level,
            'total_colors' => $total,
            'total_collisions' => $collisions,
            'collision_rate' => $total > 0 ? $collisions / $total : 0,
        ];

        file_put_contents(
            $buildPath.DIRECTORY_SEPARATOR.'naming_report.json',
            json_encode($namingReport, JSON_PRETTY_PRINT)
        );

        $manifest = [
            'build_id' => $buildId,
            'status' => 'draft',
            'public_version' => null,
            'catalog_level' => $level,
            'naming_strategy' => 'word_banks_v2',
            'total_colors' => $total,
            'shard_count' => count(glob($shardsPath.DIRECTORY_SEPARATOR.'*.sqlite') ?: []),
            'jump_nav' => $this->buildJumpNav($browseIndexPath),
            'nav_order' => config('color.nav_order'),
            'generated_at' => now()->toIso8601String(),
        ];

        file_put_contents(
            $buildPath.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );

        $this->paths->writeCurrentPointer($buildId);
    }

    private function reserveSeedNames(UsedNamesRegistry $registry): void
    {
        foreach ($this->seedIndex->all() as $seed) {
            if (isset($seed['primary_name'])) {
                $registry->reserve($seed['primary_name']);
            }
            foreach ($seed['owned_names'] ?? [] as $name) {
                $registry->reserve($name);
            }
        }
        $registry->flush();
    }

    /** @return \Generator<int, array{0: int, 1: int, 2: int}> */
    private function colorsForLevel(int $level, ?int $workerIndex = null, int $workerCount = 1): \Generator
    {
        $config = config('color.catalog_levels')[$level] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException("Unknown catalog level: {$level}");
        }

        if ($config['seeds_only'] ?? false) {
            $i = 0;
            foreach ($this->seedIndex->all() as $seed) {
                if ($workerIndex !== null && ($i % $workerCount) !== $workerIndex) {
                    $i++;
                    continue;
                }
                $i++;
                [$r, $g, $b] = ColorId::toRgb(ColorId::fromHex($seed['hex']));
                yield [$r, $g, $b];
            }

            return;
        }

        $step = $config['step'];
        $channels = $this->channelValues($step);

        foreach ($channels as $r) {
            if ($workerIndex !== null && ($r % $workerCount) !== $workerIndex) {
                continue;
            }

            foreach ($channels as $g) {
                foreach ($channels as $b) {
                    $colorId = ColorId::fromRgb($r, $g, $b);

                    if (! $this->passesLevelFilter($colorId, $config['filter'] ?? null)) {
                        continue;
                    }

                    yield [$r, $g, $b];
                }
            }
        }
    }

    private function countForLevel(int $level, ?int $workerIndex = null, int $workerCount = 1): int
    {
        $config = config('color.catalog_levels')[$level] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException("Unknown catalog level: {$level}");
        }

        if ($config['seeds_only'] ?? false) {
            $total = $this->seedIndex->count();
            if ($workerIndex === null) {
                return $total;
            }

            return intdiv($total, $workerCount) + ($workerIndex < ($total % $workerCount) ? 1 : 0);
        }

        $channels = $this->channelValues($config['step']);
        $filter = $config['filter'] ?? null;
        $count = 0;

        foreach ($channels as $r) {
            if ($workerIndex !== null && ($r % $workerCount) !== $workerIndex) {
                continue;
            }

            foreach ($channels as $g) {
                foreach ($channels as $b) {
                    if ($this->passesLevelFilter(ColorId::fromRgb($r, $g, $b), $filter)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /** @return list<int> */
    private function channelValues(int $step): array
    {
        $values = [];
        for ($value = 0; $value < 256; $value += $step) {
            $values[] = $value;
        }

        if ($values[array_key_last($values)] !== 255) {
            $values[] = 255;
        }

        return array_values(array_unique($values));
    }

    private function passesLevelFilter(int $colorId, ?string $filter): bool
    {
        return match ($filter) {
            'even_color_id' => $colorId % 2 === 0,
            'color_id_mod4' => $colorId % 4 !== 3,
            default => true,
        };
    }

    /** @param array<string, mixed> $payload */
    private function writeProgress(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
    }

    public function buildJumpNav(string $browsePath): array
    {
        if (! is_file($browsePath)) {
            return [];
        }

        $pdo = new \PDO('sqlite:'.$browsePath);
        $jumpNav = [];

        foreach (config('color.nav_order') as $bucket) {
            // Midpoint of this bucket's contiguous browse range.
            $statement = $pdo->prepare(
                'SELECT
                    MIN(row_num) AS first_row,
                    MAX(row_num) AS last_row,
                    COUNT(*) AS row_count
                 FROM browse_rows
                 WHERE hue_bucket = :bucket'
            );
            $statement->execute(['bucket' => $bucket]);
            $stats = $statement->fetch(\PDO::FETCH_ASSOC);

            if ($stats === false || (int) ($stats['row_count'] ?? 0) === 0) {
                continue;
            }

            $first = (int) $stats['first_row'];
            $last = (int) $stats['last_row'];
            $midRow = intdiv($first + $last, 2);

            $mid = $pdo->prepare(
                'SELECT row_num - 1 AS offset, color_id, hex
                 FROM browse_rows
                 WHERE row_num = :row_num
                 LIMIT 1'
            );
            $mid->execute(['row_num' => $midRow]);
            $row = $mid->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                continue;
            }

            $jumpNav[$bucket] = [
                'offset' => (int) $row['offset'],
                'color_id' => (int) $row['color_id'],
                'hex' => (string) $row['hex'],
            ];
        }

        return $jumpNav;
    }
}
