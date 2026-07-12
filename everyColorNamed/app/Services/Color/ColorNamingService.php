<?php

namespace App\Services\Color;

class ColorNamingService
{
    public function __construct(
        private readonly SeedIndex $seedIndex,
        private readonly WordBanks $wordBanks,
        private readonly UsedNamesRegistry $registry,
        private readonly bool $computeNearest = true,
        private readonly ?int $workerIndex = null,
        private readonly int $workerCount = 1,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     nearest_seed_hex: ?string,
     *     nearest_seed_name: ?string,
     *     delta_e: ?float
     * }
     */
    public function nameFor(int $r, int $g, int $b, int $colorId): array
    {
        $hex = ColorId::toHex($colorId);
        $hueBucket = ColorMath::hueBucket($r, $g, $b);
        $exact = $this->seedIndex->findExact($hex);

        $nearestHex = null;
        $nearestName = null;
        $deltaE = null;

        if ($exact !== null) {
            $nearestHex = $exact['hex'];
            $nearestName = $exact['primary_name'] ?? null;
            $deltaE = 0.0;

            $candidate = $this->shortestOwnedName($exact);
            if (
                $this->seedIndex->isCanonicalOwner($hex, $candidate)
                && $this->registry->claimReserved($candidate, $hueBucket)
            ) {
                return $this->result($candidate, $nearestHex, $nearestName, $deltaE);
            }
            // Shared seed label already owned by another hex — fall through.
        } elseif ($this->computeNearest) {
            $nearest = $this->seedIndex->findNearest($r, $g, $b);
            $nearestHex = $nearest['hex'] ?? null;
            $nearestName = $nearest['primary_name'] ?? null;
            $deltaE = $nearest['_delta_e'] ?? null;

            if ($nearest !== null && ($deltaE ?? 999) < 2) {
                $candidate = $this->shortestOwnedName($nearest);
                if ($this->registry->tryRegister($candidate, $hueBucket)) {
                    return $this->result($candidate, $nearestHex, $nearestName, $deltaE);
                }
            }
        }

        foreach ($this->candidateNames($hueBucket, $colorId) as $candidate) {
            if ($this->registry->tryRegister($candidate, $hueBucket)) {
                return $this->result($candidate, $nearestHex, $nearestName, $deltaE);
            }
        }

        throw new \RuntimeException("Unable to assign unique name for {$hex}");
    }

    /** @return \Generator<int, string> */
    private function candidateNames(string $hueBucket, int $colorId): \Generator
    {
        $words = $this->wordsForWorker($this->wordBanks->wordsForBucket($hueBucket));
        if ($words === []) {
            $words = ['Shade'];
        }

        $prefixes = $this->wordsForWorker(config('color.prefixes', []));
        $suffixes = $this->wordsForWorker(config('color.suffixes', []));
        $tokens = $this->wordsForWorker(config('color.fallback_tokens', []));
        $wordCount = count($words);
        $parallel = $this->workerCount > 1 && $this->workerIndex !== null;

        // Parallel builds: keep soft names short, then jump to injective triples.
        $baseLimit = $parallel ? min(4, $wordCount) : min(12, $wordCount);
        $bases = [];
        for ($offset = 0; $offset < $baseLimit; $offset++) {
            $bases[] = $words[($colorId + $offset) % $wordCount];
        }

        foreach ($bases as $base) {
            yield $base;
        }

        foreach ($bases as $base) {
            $prefix = $prefixes[$colorId % max(count($prefixes), 1)] ?? null;
            $suffix = $suffixes[$colorId % max(count($suffixes), 1)] ?? null;
            if ($prefix) {
                yield "{$prefix} {$base}";
            }
            if ($suffix) {
                yield "{$base} {$suffix}";
            }
            if ($prefix && $suffix) {
                yield "{$prefix} {$base} {$suffix}";
            }
        }

        if (! $parallel) {
            $primary = $bases[0];
            foreach ($prefixes as $prefix) {
                yield "{$prefix} {$primary}";
                foreach ($suffixes as $suffix) {
                    yield "{$prefix} {$primary} {$suffix}";
                }
            }
            foreach ($suffixes as $suffix) {
                yield "{$primary} {$suffix}";
            }

            $pool = array_values(array_unique(array_merge($words, $tokens)));
            $poolCount = max(count($pool), 1);
            for ($i = 0; $i < min(24, $poolCount); $i++) {
                $secondary = $pool[($colorId + $i * 7) % $poolCount];
                if ($secondary === $primary) {
                    continue;
                }
                yield "{$primary} {$secondary}";
                foreach (array_slice($prefixes, 0, 4) as $prefix) {
                    yield "{$prefix} {$primary} {$secondary}";
                }
            }
        } else {
            // A few two-word soft names, then unique triples (avoids millions of collisions).
            $primary = $bases[0];
            $pool = array_values(array_unique(array_merge($words, $tokens)));
            $poolCount = max(count($pool), 1);
            for ($i = 0; $i < min(8, $poolCount); $i++) {
                $secondary = $pool[($colorId + $i * 7) % $poolCount];
                if ($secondary === $primary) {
                    continue;
                }
                yield "{$primary} {$secondary}";
            }
        }

        // Globally unique real-word triples: 256^3 == 16,777,216.
        // Primary mapping is injective in color_id; retries use a mix so we
        // never steal a neighboring color's primary triple.
        yield $this->uniqueTriple($colorId);
        for ($n = 1; $n < 64; $n++) {
            yield $this->uniqueTriple(($colorId * 1_664_525 + $n * 1_013_904_223) & 0xFFFFFF);
        }
    }

    private function uniqueTriple(int $colorId): string
    {
        $lexicon = $this->wordBanks->lexicon();
        if (count($lexicon) < 256) {
            $lexicon = array_values(array_unique(array_merge(
                $this->wordBanks->allWords(),
                config('color.fallback_tokens', []),
                ['Shade', 'Tone', 'Tint', 'Hue', 'Wash', 'Cast'],
            )));
        }

        $n = count($lexicon);
        $colorId = ($colorId & 0xFFFFFF) % ($n * $n * $n);

        $a = $lexicon[$colorId % $n];
        $b = $lexicon[intdiv($colorId, $n) % $n];
        $c = $lexicon[intdiv($colorId, $n * $n) % $n];

        // Avoid obvious "Word Word Word" / "Word Word X" repetition.
        if ($b === $a) {
            $b = $lexicon[($colorId + 1) % $n];
        }
        if ($c === $a || $c === $b) {
            $c = $lexicon[($colorId + 2) % $n];
        }

        return "{$a} {$b} {$c}";
    }

    /**
     * When running parallel workers, partition soft vocabulary so workers
     * cannot claim the same short names.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function wordsForWorker(array $words): array
    {
        if ($this->workerCount <= 1 || $this->workerIndex === null) {
            return array_values($words);
        }

        $filtered = [];
        foreach ($words as $index => $word) {
            $slot = (crc32(strtolower($word)) & 0xffffffff) % $this->workerCount;
            if ($slot === $this->workerIndex) {
                $filtered[] = $word;
            }
        }

        // Never fall back to the full list (that reintroduces cross-worker collisions).
        if ($filtered !== []) {
            return $filtered;
        }

        $fallback = [];
        foreach (array_values($words) as $index => $word) {
            if (($index % $this->workerCount) === $this->workerIndex) {
                $fallback[] = $word;
            }
        }

        return $fallback !== [] ? $fallback : array_values($words);
    }

    /**
     * @return array{
     *     name: string,
     *     nearest_seed_hex: ?string,
     *     nearest_seed_name: ?string,
     *     delta_e: ?float
     * }
     */
    private function result(string $name, ?string $nearestHex, ?string $nearestName, ?float $deltaE): array
    {
        return [
            'name' => $name,
            'nearest_seed_hex' => $nearestHex,
            'nearest_seed_name' => $nearestName,
            'delta_e' => $deltaE,
        ];
    }

    private function shortestOwnedName(array $seed): string
    {
        $options = $seed['owned_names'] ?? [];

        if ($options === [] && isset($seed['primary_name'])) {
            $options = [$seed['primary_name']];
        }

        if (($options[0] ?? null) === null && isset($seed['aliases'][0]['name'])) {
            $options = [$seed['aliases'][0]['name']];
        }

        usort($options, fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: $a <=> $b);

        return $options[0] ?? ($seed['primary_name'] ?? 'Color');
    }
}
