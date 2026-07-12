<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use Illuminate\Console\Command;

class ReportAliasesCommand extends Command
{
    protected $signature = 'colors:report-aliases';

    protected $description = 'Report seed colors with multiple names (same hex)';

    public function handle(DataPaths $paths): int
    {
        $payload = json_decode(file_get_contents($paths->mergedSeeds()), true, flags: JSON_THROW_ON_ERROR);
        $rows = array_filter($payload['seeds'], fn (array $seed): bool => count($seed['aliases'] ?? []) > 1);

        usort($rows, fn (array $a, array $b): int => count($b['aliases']) <=> count($a['aliases']));

        foreach ($rows as $seed) {
            $aliasText = collect($seed['aliases'])
                ->map(fn (array $alias): string => "{$alias['name']} ({$alias['source']})")
                ->implode(', ');

            $this->line("#{$seed['hex']} — {$seed['primary_name']} — AKA: {$aliasText}");
        }

        $this->newLine();
        $this->components->info(count($rows).' seeds with multiple aliases.');

        return self::SUCCESS;
    }
}
