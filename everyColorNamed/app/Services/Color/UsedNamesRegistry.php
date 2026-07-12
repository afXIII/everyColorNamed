<?php

namespace App\Services\Color;

use PDO;

class UsedNamesRegistry
{
    private PDO $pdo;

    private int $collisionCount = 0;

    /** @var array<string, true> */
    private array $used = [];

    /** Names reserved for seeds but not yet assigned to a color. */
    /** @var array<string, true> */
    private array $reservedUnclaimed = [];

    /** @var list<array{0: string, 1: string}> */
    private array $pendingInserts = [];

    /** @var array<string, int> */
    private array $wordUsage = [];

    private int $flushEvery;

    public function __construct(string $databasePath, int $flushEvery = 5000)
    {
        if (is_file($databasePath)) {
            unlink($databasePath);
        }

        $this->flushEvery = $flushEvery;
        $this->pdo = new PDO('sqlite:'.$databasePath);
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = OFF');
        $this->pdo->exec('PRAGMA temp_store = MEMORY');
        $this->pdo->exec('CREATE TABLE used_names (normalized TEXT PRIMARY KEY, display TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE word_usage (word TEXT NOT NULL, word_type TEXT NOT NULL, hue_bucket TEXT, use_count INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (word, word_type, hue_bucket))');
    }

    public function isAvailable(string $name): bool
    {
        return ! isset($this->used[$this->normalize($name)]);
    }

    public function register(string $name, string $hueBucket): void
    {
        if (! $this->tryRegister($name, $hueBucket)) {
            throw new \RuntimeException("Duplicate name rejected: {$name}");
        }
    }

    public function tryRegister(string $name, string $hueBucket): bool
    {
        $normalized = $this->normalize($name);

        if (isset($this->used[$normalized])) {
            $this->collisionCount++;

            return false;
        }

        $this->used[$normalized] = true;
        $this->pendingInserts[] = [$normalized, $name];
        $this->recordWordUsage($name, $hueBucket);

        if (count($this->pendingInserts) >= $this->flushEvery) {
            $this->flush();
        }

        return true;
    }

    private function recordWordUsage(string $name, string $hueBucket): void
    {
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        $tokenCount = count($tokens);
        $prefixes = config('color.prefixes', []);
        $suffixes = config('color.suffixes', []);

        foreach ($tokens as $index => $token) {
            $type = match (true) {
                $index === 0 && in_array($token, $prefixes, true) => 'prefix',
                $index === $tokenCount - 1 && in_array($token, $suffixes, true) => 'suffix',
                default => 'base',
            };

            $key = $token."\0".$type."\0".$hueBucket;
            $this->wordUsage[$key] = ($this->wordUsage[$key] ?? 0) + 1;
        }
    }

    /** Reserve a name without counting usage (e.g. seed names before parallel workers run). */
    public function reserve(string $name): void
    {
        $normalized = $this->normalize($name);
        if (isset($this->used[$normalized])) {
            return;
        }

        $this->used[$normalized] = true;
        $this->reservedUnclaimed[$normalized] = true;
        $this->pendingInserts[] = [$normalized, $name];

        if (count($this->pendingInserts) >= $this->flushEvery) {
            $this->flush();
        }
    }

    /**
     * Claim a reserved seed name for exactly one color.
     * Duplicate seed labels (same name, different hex) fall through to soft naming.
     */
    public function claimReserved(string $name, string $hueBucket): bool
    {
        $normalized = $this->normalize($name);

        if (isset($this->reservedUnclaimed[$normalized])) {
            unset($this->reservedUnclaimed[$normalized]);
            $this->recordWordUsage($name, $hueBucket);

            return true;
        }

        if (isset($this->used[$normalized])) {
            $this->collisionCount++;

            return false;
        }

        return $this->tryRegister($name, $hueBucket);
    }

    /** @deprecated Use claimReserved() for seed exact matches. */
    public function ensureRegistered(string $name, string $hueBucket): bool
    {
        return $this->claimReserved($name, $hueBucket);
    }

    public function collisionCount(): int
    {
        return $this->collisionCount;
    }

    public function count(): int
    {
        return count($this->used);
    }

    public function flush(): void
    {
        if ($this->pendingInserts === []) {
            return;
        }

        $this->pdo->beginTransaction();
        $statement = $this->pdo->prepare('INSERT INTO used_names (normalized, display) VALUES (?, ?)');

        foreach ($this->pendingInserts as [$normalized, $display]) {
            $statement->execute([$normalized, $display]);
        }

        $this->pdo->commit();
        $this->pendingInserts = [];
    }

    public function finalize(): void
    {
        $this->flush();

        $this->pdo->beginTransaction();
        $statement = $this->pdo->prepare(
            'INSERT INTO word_usage (word, word_type, hue_bucket, use_count) VALUES (?, ?, ?, ?)
             ON CONFLICT(word, word_type, hue_bucket) DO UPDATE SET use_count = excluded.use_count'
        );

        foreach ($this->wordUsage as $key => $count) {
            [$word, $type, $hueBucket] = explode("\0", $key);
            $statement->execute([$word, $type, $hueBucket, $count]);
        }

        $this->pdo->commit();
    }

    /** @return list<array{word: string, word_type: string, hue_bucket: string, use_count: int}> */
    public function topWordUsage(int $limit = 20): array
    {
        $this->finalize();

        $statement = $this->pdo->query('SELECT word, word_type, hue_bucket, use_count FROM word_usage ORDER BY use_count DESC LIMIT '.$limit);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function database(): PDO
    {
        return $this->pdo;
    }

    private function normalize(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }
}
