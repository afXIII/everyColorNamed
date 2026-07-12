<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use Illuminate\Console\Command;
use PDO;

class VerifyUniqueCommand extends Command
{
    protected $signature = 'colors:verify-unique {buildId? : Build ID to verify (defaults to data/current)}';

    protected $description = 'Verify that all color names are unique within a build';

    public function handle(DataPaths $paths): int
    {
        $buildId = $this->argument('buildId') ?? $paths->currentPointer();
        $buildPath = str_starts_with($buildId, 'releases/')
            ? $paths->catalogRoot($buildId)
            : $paths->build($buildId);
        $shardsPath = $buildPath.DIRECTORY_SEPARATOR.'shards';
        $names = [];
        $duplicates = [];

        foreach (glob($shardsPath.DIRECTORY_SEPARATOR.'*.sqlite') as $shardFile) {
            $pdo = new PDO('sqlite:'.$shardFile);
            $rows = $pdo->query('SELECT hex, name FROM colors')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $key = strtolower($row['name']);
                if (isset($names[$key])) {
                    $duplicates[] = [
                        'name' => $row['name'],
                        'hex_a' => $names[$key],
                        'hex_b' => $row['hex'],
                    ];
                } else {
                    $names[$key] = $row['hex'];
                }
            }
        }

        if ($duplicates !== []) {
            foreach ($duplicates as $duplicate) {
                $this->components->error("Duplicate name {$duplicate['name']}: #{$duplicate['hex_a']} and #{$duplicate['hex_b']}");
            }

            return self::FAILURE;
        }

        $this->components->info('Verified '.number_format(count($names)).' unique names in build '.$buildId);

        return self::SUCCESS;
    }
}
