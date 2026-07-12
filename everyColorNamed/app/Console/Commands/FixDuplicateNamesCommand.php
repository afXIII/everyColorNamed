<?php

namespace App\Console\Commands;

use App\Services\Color\ColorId;
use App\Services\Color\DataPaths;
use App\Services\Color\WordBanks;
use Illuminate\Console\Command;
use PDO;

class FixDuplicateNamesCommand extends Command
{
    protected $signature = 'colors:fix-duplicate-names {buildId? : Build ID (defaults to data/current)}';

    protected $description = 'Rename colliding names in-place (keep first owner, reassign the rest)';

    public function handle(DataPaths $paths, WordBanks $wordBanks): int
    {
        $buildId = $this->argument('buildId') ?? $paths->currentPointer();
        $buildPath = $paths->build($buildId);
        $shardsPath = $buildPath.DIRECTORY_SEPARATOR.'shards';
        $browsePath = $buildPath.DIRECTORY_SEPARATOR.'browse.sqlite';

        if (! is_dir($shardsPath)) {
            $this->components->error("Missing shards for build {$buildId}");

            return self::FAILURE;
        }

        $wordBanks->loadLexicon($paths->uniqueLexicon());
        $lexicon = $wordBanks->lexicon();
        if (count($lexicon) < 256) {
            $this->components->error('unique_lexicon.json needs at least 256 words');

            return self::FAILURE;
        }

        /** @var array<string, array{hex: string, shard: string, color_id: int}> $owners */
        $owners = [];
        /** @var list<array{hex: string, shard: string, color_id: int, name: string}> $losers */
        $losers = [];

        foreach (glob($shardsPath.DIRECTORY_SEPARATOR.'*.sqlite') ?: [] as $shardFile) {
            $pdo = new PDO('sqlite:'.$shardFile);
            $rows = $pdo->query('SELECT color_id, hex, name FROM colors')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = strtolower((string) $row['name']);
                $entry = [
                    'hex' => (string) $row['hex'],
                    'shard' => $shardFile,
                    'color_id' => (int) $row['color_id'],
                    'name' => (string) $row['name'],
                ];
                if (! isset($owners[$key])) {
                    $owners[$key] = $entry;
                } else {
                    $losers[] = $entry;
                }
            }
        }

        if ($losers === []) {
            $this->components->info('No duplicate names in build '.$buildId);

            return self::SUCCESS;
        }

        $this->components->info(count($losers).' colors need renaming ('.count($losers).' colliding names kept by first owner)');

        /** @var array<string, true> $used */
        $used = array_fill_keys(array_keys($owners), true);

        $renames = [];
        foreach ($losers as $loser) {
            $newName = $this->nextUniqueName((int) $loser['color_id'], $lexicon, $used);
            $used[strtolower($newName)] = true;
            $renames[] = [...$loser, 'new_name' => $newName];
        }

        /** @var array<string, list<array{hex: string, new_name: string}>> $byShard */
        $byShard = [];
        foreach ($renames as $rename) {
            $byShard[$rename['shard']][] = [
                'hex' => $rename['hex'],
                'new_name' => $rename['new_name'],
            ];
        }

        foreach ($byShard as $shardFile => $rows) {
            $pdo = new PDO('sqlite:'.$shardFile);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->beginTransaction();
            $statement = $pdo->prepare('UPDATE colors SET name = :name WHERE hex = :hex');
            foreach ($rows as $row) {
                $statement->execute(['name' => $row['new_name'], 'hex' => $row['hex']]);
            }
            $pdo->commit();
        }

        if (is_file($browsePath)) {
            $browse = new PDO('sqlite:'.$browsePath);
            $browse->exec('PRAGMA journal_mode = WAL');
            $browse->beginTransaction();
            $statement = $browse->prepare('UPDATE browse_rows SET name = :name WHERE hex = :hex');
            foreach ($renames as $rename) {
                $statement->execute(['name' => $rename['new_name'], 'hex' => $rename['hex']]);
            }
            $browse->commit();
        }

        foreach (array_slice($renames, 0, 10) as $rename) {
            $this->line("  #{$rename['hex']}: {$rename['name']} → {$rename['new_name']}");
        }
        if (count($renames) > 10) {
            $this->line('  … and '.(count($renames) - 10).' more');
        }

        $this->components->info('Renamed '.count($renames).' colors in build '.$buildId);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $lexicon
     * @param  array<string, true>  $used
     */
    private function nextUniqueName(int $colorId, array $lexicon, array $used): string
    {
        $n = count($lexicon);
        for ($attempt = 0; $attempt < 4096; $attempt++) {
            $id = ($colorId * 1_664_525 + ($attempt + 1) * 1_013_904_223) & 0xFFFFFF;
            $a = $lexicon[$id % $n];
            $b = $lexicon[intdiv($id, $n) % $n];
            $c = $lexicon[intdiv($id, $n * $n) % $n];
            if ($b === $a) {
                $b = $lexicon[($id + 1) % $n];
            }
            if ($c === $a || $c === $b) {
                $c = $lexicon[($id + 2) % $n];
            }
            $name = "{$a} {$b} {$c}";
            if (! isset($used[strtolower($name)])) {
                return $name;
            }
        }

        // Extremely unlikely fallback — still a real-word-ish unique string.
        return sprintf('Shade Tone %s', ColorId::toHex($colorId));
    }
}
