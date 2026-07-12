<?php

namespace App\Services\Color;

use PDO;

class SeedHexIndex
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly DataPaths $paths,
    ) {}

    public function indexPath(): string
    {
        return $this->paths->seedsByHexIndex();
    }

    public function buildFromMerged(string $mergedPath): void
    {
        $payload = json_decode(file_get_contents($mergedPath), true, flags: JSON_THROW_ON_ERROR);
        $indexPath = $this->indexPath();

        if (is_file($indexPath)) {
            unlink($indexPath);
        }

        $pdo = new PDO('sqlite:'.$indexPath);
        $pdo->exec('CREATE TABLE seeds (hex TEXT PRIMARY KEY, payload TEXT NOT NULL)');
        $statement = $pdo->prepare('INSERT INTO seeds (hex, payload) VALUES (:hex, :payload)');

        foreach ($payload['seeds'] ?? [] as $seed) {
            $statement->execute([
                'hex' => $seed['hex'],
                'payload' => json_encode($seed, flags: JSON_THROW_ON_ERROR),
            ]);
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS seeds_hex_idx ON seeds (hex)');
        $this->pdo = null;
    }

    public function findExact(string $hex): ?array
    {
        $hex = strtoupper(ltrim($hex, '#'));
        $indexPath = $this->indexPath();

        if (! is_file($indexPath)) {
            return null;
        }

        $pdo = $this->connection($indexPath);
        $statement = $pdo->prepare('SELECT payload FROM seeds WHERE hex = :hex LIMIT 1');
        $statement->execute(['hex' => $hex]);
        $payload = $statement->fetchColumn();

        if ($payload === false) {
            return null;
        }

        return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }

    private function connection(string $indexPath): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO('sqlite:'.$indexPath);
        }

        return $this->pdo;
    }
}
