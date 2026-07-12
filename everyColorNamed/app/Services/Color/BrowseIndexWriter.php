<?php

namespace App\Services\Color;

use PDO;

class BrowseIndexWriter
{
    public function build(string $shardsPath, string $outputPath): void
    {
        if (is_file($outputPath)) {
            unlink($outputPath);
        }

        $bucketOrder = array_flip(config('color.nav_order'));

        $pdo = new PDO('sqlite:'.$outputPath);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = OFF');

        // Stage rows with a perceptual sort key, then copy in sorted order so
        // row_num (and therefore browse offsets) follow bucket/hue/lightness
        // instead of raw color_id.
        $pdo->exec(<<<'SQL'
            CREATE TABLE staging (
                sort_key INTEGER NOT NULL,
                color_id INTEGER NOT NULL,
                hex TEXT NOT NULL,
                name TEXT NOT NULL,
                hue_bucket TEXT NOT NULL,
                text_contrast TEXT NOT NULL,
                r INTEGER NOT NULL,
                g INTEGER NOT NULL,
                b INTEGER NOT NULL
            )
        SQL);

        $insert = $pdo->prepare(
            'INSERT INTO staging (sort_key, color_id, hex, name, hue_bucket, text_contrast, r, g, b)
             VALUES (:sort_key, :color_id, :hex, :name, :hue_bucket, :text_contrast, :r, :g, :b)'
        );

        $shardFiles = glob($shardsPath.DIRECTORY_SEPARATOR.'*.sqlite') ?: [];
        sort($shardFiles);

        $pdo->beginTransaction();

        foreach ($shardFiles as $shardFile) {
            $shard = new PDO('sqlite:'.$shardFile);
            $rows = $shard->query('SELECT color_id, hex, name, hue_bucket, text_contrast, r, g, b FROM colors ORDER BY color_id ASC');

            foreach ($rows as $row) {
                $bucketIndex = $bucketOrder[$row['hue_bucket']] ?? count($bucketOrder);

                $insert->execute([
                    'sort_key' => ColorMath::browseSortKey((int) $row['r'], (int) $row['g'], (int) $row['b'], $bucketIndex),
                    'color_id' => $row['color_id'],
                    'hex' => $row['hex'],
                    'name' => $row['name'],
                    'hue_bucket' => $row['hue_bucket'],
                    'text_contrast' => $row['text_contrast'],
                    'r' => $row['r'],
                    'g' => $row['g'],
                    'b' => $row['b'],
                ]);
            }
        }

        $pdo->commit();

        $pdo->exec(<<<'SQL'
            CREATE TABLE browse_rows (
                row_num INTEGER PRIMARY KEY AUTOINCREMENT,
                color_id INTEGER NOT NULL,
                hex TEXT NOT NULL,
                name TEXT NOT NULL,
                hue_bucket TEXT NOT NULL,
                text_contrast TEXT NOT NULL,
                r INTEGER NOT NULL,
                g INTEGER NOT NULL,
                b INTEGER NOT NULL
            )
        SQL);

        $pdo->exec(<<<'SQL'
            INSERT INTO browse_rows (color_id, hex, name, hue_bucket, text_contrast, r, g, b)
            SELECT color_id, hex, name, hue_bucket, text_contrast, r, g, b
            FROM staging ORDER BY sort_key ASC, color_id ASC
        SQL);

        $pdo->exec('DROP TABLE staging');
        $pdo->exec('CREATE INDEX idx_browse_color_id ON browse_rows(color_id)');
        $pdo->exec('CREATE INDEX idx_browse_hex ON browse_rows(hex)');
        $pdo->exec('VACUUM');
    }
}
