<?php

namespace App\Services\Color;

class SeedMerger
{
    public function __construct(
        private readonly DataPaths $paths,
    ) {}

    /**
     * @param  list<array{hex: string, name: string, source: string, source_priority: int}>  $rows
     * @return array{seeds: list<array<string, mixed>>, name_index: array<string, list<array<string, mixed>>>}
     */
    public function merge(array $rows): array
    {
        $rows = array_values(array_filter(
            $rows,
            fn (array $row): bool => SourcePriority::isEnglishSource($row['source']),
        ));

        /** @var array<string, list<array{hex: string, name: string, source: string, source_priority: int, normalized_name: string}>> $byHex */
        $byHex = [];

        foreach ($rows as $row) {
            $normalizedName = $this->normalizeName($row['name']);
            $byHex[$row['hex']][] = [
                ...$row,
                'normalized_name' => $normalizedName,
            ];
        }

        /** @var array<string, list<array{hex: string, source: string, source_priority: int}>> $nameToHexes */
        $nameToHexes = [];

        foreach ($byHex as $hex => $entries) {
            foreach ($entries as $entry) {
                $nameToHexes[$entry['normalized_name']][] = [
                    'hex' => $hex,
                    'source' => $entry['source'],
                    'source_priority' => $entry['source_priority'],
                    'name' => $entry['name'],
                ];
            }
        }

        $canonicalHexForName = [];
        foreach ($nameToHexes as $normalizedName => $claims) {
            usort($claims, function (array $a, array $b): int {
                return [$a['source_priority'], $a['hex']] <=> [$b['source_priority'], $b['hex']];
            });

            $canonicalHexForName[$normalizedName] = $claims[0]['hex'];
        }

        $seeds = [];

        foreach ($byHex as $hex => $entries) {
            usort($entries, function (array $a, array $b): int {
                return [$a['source_priority'], $a['name']] <=> [$b['source_priority'], $b['name']];
            });

            $aliases = [];
            $seenAlias = [];

            foreach ($entries as $entry) {
                $aliasKey = strtolower($entry['name'].'|'.$entry['source']);
                if (isset($seenAlias[$aliasKey])) {
                    continue;
                }

                $seenAlias[$aliasKey] = true;
                $aliases[] = [
                    'name' => $entry['name'],
                    'source' => SourcePriority::displayName($entry['source']),
                    'source_key' => $entry['source'],
                ];
            }

            $ownedNames = [];
            $conflictingNames = [];

            foreach ($entries as $entry) {
                $normalizedName = $entry['normalized_name'];
                $canonicalHex = $canonicalHexForName[$normalizedName] ?? $hex;

                if ($canonicalHex === $hex) {
                    if (! in_array($entry['name'], $ownedNames, true)) {
                        $ownedNames[] = $entry['name'];
                    }

                    continue;
                }

                $canonicalSource = $this->bestSourceForHex($entries, $canonicalHexForName, $normalizedName, $nameToHexes);

                $conflictingNames[] = [
                    'name' => $entry['name'],
                    'source' => SourcePriority::displayName($entry['source']),
                    'source_key' => $entry['source'],
                    'canonical_hex' => $canonicalHex,
                    'canonical_source' => $canonicalSource,
                ];
            }

            $primaryName = $this->choosePrimaryName($aliases, $ownedNames);

            [$r, $g, $b] = ColorId::toRgb(ColorId::fromHex($hex));
            $lab = ColorMath::rgbToLab($r, $g, $b);

            $seeds[] = [
                'hex' => $hex,
                'primary_name' => $primaryName,
                'owned_names' => array_values(array_unique($ownedNames)),
                'aliases' => $aliases,
                'conflicting_names' => $this->uniqueConflicts($conflictingNames),
                'lab' => ['l' => $lab[0], 'a' => $lab[1], 'b' => $lab[2]],
            ];
        }

        usort($seeds, fn (array $a, array $b): int => $a['hex'] <=> $b['hex']);

        $payload = [
            'merged_at' => now()->toIso8601String(),
            'seed_count' => count($seeds),
            'seeds' => $seeds,
        ];

        file_put_contents(
            $this->paths->mergedSeeds(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return [
            'seeds' => $seeds,
            'name_index' => $nameToHexes,
        ];
    }

    private function choosePrimaryName(array $aliases, array $ownedNames): string
    {
        foreach ($aliases as $alias) {
            if (in_array($alias['name'], $ownedNames, true)) {
                if (! str_contains($alias['name'], ' ')) {
                    return $alias['name'];
                }
            }
        }

        foreach ($ownedNames as $name) {
            if (! str_contains($name, ' ')) {
                return $name;
            }
        }

        return $ownedNames[0] ?? $aliases[0]['name'] ?? 'Unknown';
    }

    private function bestSourceForHex(array $entries, array $canonicalHexForName, string $normalizedName, array $nameToHexes): string
    {
        $canonicalHex = $canonicalHexForName[$normalizedName];
        $claims = $nameToHexes[$normalizedName] ?? [];
        foreach ($claims as $claim) {
            if ($claim['hex'] === $canonicalHex) {
                return SourcePriority::displayName($claim['source']);
            }
        }

        return SourcePriority::displayName($entries[0]['source']);
    }

    private function uniqueConflicts(array $conflicts): array
    {
        $unique = [];
        $seen = [];

        foreach ($conflicts as $conflict) {
            $key = strtolower($conflict['name'].'|'.$conflict['source_key']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $conflict;
        }

        return $unique;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }
}
