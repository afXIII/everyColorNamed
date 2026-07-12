<?php

namespace App\Services\Color;

class SeedIndex
{
    /** @var array<string, array<string, mixed>> */
    private array $byHex = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $byHueBucket = [];

    /** Normalized name → canonical hex (lowest hex wins for shared labels). */
    /** @var array<string, string> */
    private array $canonicalHexByName = [];

    public function loadFromFile(string $path): void
    {
        $payload = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->load($payload['seeds'] ?? []);
    }

    /** @param list<array<string, mixed>> $seeds */
    public function load(array $seeds): void
    {
        $this->byHex = [];
        $this->byHueBucket = [];
        $this->canonicalHexByName = [];

        foreach ($seeds as $seed) {
            $hex = $seed['hex'];

            if (! isset($seed['lab'])) {
                [$r, $g, $b] = ColorId::toRgb(ColorId::fromHex($hex));
                $lab = ColorMath::rgbToLab($r, $g, $b);
                $seed['lab'] = ['l' => $lab[0], 'a' => $lab[1], 'b' => $lab[2]];
            }

            $this->byHex[$hex] = $seed;

            [$r, $g, $b] = ColorId::toRgb(ColorId::fromHex($hex));
            $bucket = ColorMath::hueBucket($r, $g, $b);
            $this->byHueBucket[$bucket][] = $seed;

            foreach ($this->ownedNames($seed) as $name) {
                $key = $this->normalizeName($name);
                if (! isset($this->canonicalHexByName[$key]) || $hex < $this->canonicalHexByName[$key]) {
                    $this->canonicalHexByName[$key] = $hex;
                }
            }
        }
    }

    /** Whether this hex is the sole owner allowed to keep a shared seed label. */
    public function isCanonicalOwner(string $hex, string $name): bool
    {
        $key = $this->normalizeName($name);

        return ($this->canonicalHexByName[$key] ?? null) === $hex;
    }

    /** @return list<string> */
    private function ownedNames(array $seed): array
    {
        $names = $seed['owned_names'] ?? [];
        if ($names === [] && isset($seed['primary_name'])) {
            $names = [$seed['primary_name']];
        }

        return array_values(array_filter($names, fn ($n) => is_string($n) && $n !== ''));
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    public function findExact(string $hex): ?array
    {
        return $this->byHex[$hex] ?? null;
    }

    public function findNearest(int $r, int $g, int $b): ?array
    {
        $bucket = ColorMath::hueBucket($r, $g, $b);
        $candidates = $this->byHueBucket[$bucket] ?? [];

        if ($candidates === []) {
            $candidates = array_merge(...array_values($this->byHueBucket));
        }

        $lab = ColorMath::rgbToLab($r, $g, $b);
        $best = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($candidates as $seed) {
            $seedLab = [
                $seed['lab']['l'],
                $seed['lab']['a'],
                $seed['lab']['b'],
            ];
            $distance = ColorMath::deltaE($lab, $seedLab);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $seed;
                $best['_delta_e'] = $distance;
            }
        }

        return $best;
    }

    public function count(): int
    {
        return count($this->byHex);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_values($this->byHex);
    }
}
