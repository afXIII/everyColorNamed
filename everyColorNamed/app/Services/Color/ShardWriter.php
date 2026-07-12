<?php

namespace App\Services\Color;

use PDO;

class ShardWriter
{
    /** @var array<string, PDO> */
    private array $connections = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $buffers = [];

    private int $buffered = 0;

    private int $flushEvery;

    public function __construct(private readonly string $shardsDirectory, int $flushEvery = 2000)
    {
        $this->flushEvery = $flushEvery;

        if (! is_dir($shardsDirectory)) {
            mkdir($shardsDirectory, 0755, true);
        }
    }

    public function insert(array $row): void
    {
        $shardKey = ColorId::shardKey($row['color_id']);
        $this->buffers[$shardKey][] = $row;
        $this->buffered++;

        if ($this->buffered >= $this->flushEvery) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffered === 0) {
            return;
        }

        foreach ($this->buffers as $shardKey => $rows) {
            if ($rows === []) {
                continue;
            }

            $pdo = $this->connection($shardKey);
            $pdo->beginTransaction();

            $statement = $pdo->prepare(
                'INSERT INTO colors (
                    color_id, r, g, b, hex, name, hue_bucket, text_contrast,
                    nearest_seed_hex, nearest_seed_name, delta_e, l, a, b_lab
                ) VALUES (
                    :color_id, :r, :g, :b, :hex, :name, :hue_bucket, :text_contrast,
                    :nearest_seed_hex, :nearest_seed_name, :delta_e, :l, :a, :b_lab
                )'
            );

            foreach ($rows as $row) {
                $statement->execute([
                    'color_id' => $row['color_id'],
                    'r' => $row['r'],
                    'g' => $row['g'],
                    'b' => $row['b'],
                    'hex' => $row['hex'],
                    'name' => $row['name'],
                    'hue_bucket' => $row['hue_bucket'],
                    'text_contrast' => $row['text_contrast'],
                    'nearest_seed_hex' => $row['nearest_seed_hex'],
                    'nearest_seed_name' => $row['nearest_seed_name'],
                    'delta_e' => $row['delta_e'],
                    'l' => $row['l'],
                    'a' => $row['a'],
                    'b_lab' => $row['b_lab'],
                ]);
            }

            $pdo->commit();
        }

        $this->buffers = [];
        $this->buffered = 0;
    }

    public function finalize(): void
    {
        $this->flush();

        foreach ($this->connections as $pdo) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_colors_hex ON colors(hex)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_colors_name ON colors(name)');
        }
    }

    public function shardPaths(): array
    {
        return array_map(
            fn (string $key): string => $this->shardsDirectory.DIRECTORY_SEPARATOR.$key.'.sqlite',
            array_keys($this->connections),
        );
    }

    private function connection(string $shardKey): PDO
    {
        if (isset($this->connections[$shardKey])) {
            return $this->connections[$shardKey];
        }

        $path = $this->shardsDirectory.DIRECTORY_SEPARATOR.$shardKey.'.sqlite';
        if (is_file($path)) {
            unlink($path);
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = OFF');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        $pdo->exec(<<<'SQL'
            CREATE TABLE colors (
                color_id INTEGER PRIMARY KEY,
                r INTEGER NOT NULL,
                g INTEGER NOT NULL,
                b INTEGER NOT NULL,
                hex TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL UNIQUE,
                hue_bucket TEXT NOT NULL,
                text_contrast TEXT NOT NULL,
                nearest_seed_hex TEXT,
                nearest_seed_name TEXT,
                delta_e REAL,
                l REAL,
                a REAL,
                b_lab REAL
            )
        SQL);

        $this->connections[$shardKey] = $pdo;

        return $pdo;
    }
}
