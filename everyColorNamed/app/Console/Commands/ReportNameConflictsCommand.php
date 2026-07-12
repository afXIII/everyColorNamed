<?php

namespace App\Console\Commands;

use App\Services\Color\DataPaths;
use Illuminate\Console\Command;

class ReportNameConflictsCommand extends Command
{
    protected $signature = 'colors:report-name-conflicts';

    protected $description = 'Report names claimed by multiple hex values across sources';

    public function handle(DataPaths $paths): int
    {
        $payload = json_decode(file_get_contents($paths->mergedSeeds()), true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, list<array<string, string>>> $groups */
        $groups = [];

        foreach ($payload['seeds'] as $seed) {
            foreach ($seed['owned_names'] ?? [] as $name) {
                $groups[strtolower($name)][] = [
                    'hex' => $seed['hex'],
                    'role' => 'CANONICAL',
                    'source' => 'merged',
                ];
            }

            foreach ($seed['conflicting_names'] ?? [] as $conflict) {
                $groups[strtolower($conflict['name'])][] = [
                    'hex' => $seed['hex'],
                    'role' => 'CONFLICT',
                    'source' => $conflict['source'],
                    'canonical_hex' => $conflict['canonical_hex'],
                ];
            }
        }

        $conflictNames = array_filter($groups, fn (array $entries): bool => count($entries) > 1);

        ksort($conflictNames);

        foreach ($conflictNames as $name => $entries) {
            $this->line(strtoupper($name));
            foreach ($entries as $entry) {
                $suffix = isset($entry['canonical_hex']) ? " → canonical #{$entry['canonical_hex']}" : '';
                $this->line("  {$entry['role']}  #{$entry['hex']}  {$entry['source']}{$suffix}");
            }
            $this->newLine();
        }

        $this->components->info(count($conflictNames).' names with cross-hex claims.');

        return self::SUCCESS;
    }
}
