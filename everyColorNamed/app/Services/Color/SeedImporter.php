<?php

namespace App\Services\Color;

use Illuminate\Support\Facades\Http;

class SeedImporter
{
    /** @var list<array{hex: string, name: string, source: string, source_priority: int}> */
    private array $rows = [];

    public function __construct(
        private readonly DataPaths $paths,
    ) {}

    public function import(): array
    {
        $this->rows = [];

        $this->importColorLists();
        $this->importColorNameList();
        $this->importXkcd();
        $this->importResene();

        $this->paths->ensureDirectory('seeds', 'raw');
        file_put_contents(
            $this->paths->joinRawManifest(),
            json_encode([
                'imported_at' => now()->toIso8601String(),
                'row_count' => count($this->rows),
            ], JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $this->paths->joinRawRows(),
            json_encode($this->rows, JSON_PRETTY_PRINT)
        );

        return $this->rows;
    }

    /** @return list<array{hex: string, name: string, source: string, source_priority: int}> */
    public function rows(): array
    {
        return $this->rows;
    }

    private function importColorLists(): void
    {
        $response = Http::timeout(120)->get(config('color.urls.colorlists'));
        $response->throw();
        $payload = $response->json();

        foreach ($payload['lists'] ?? [] as $listKey => $colors) {
            if (! SourcePriority::isEnglishSource($listKey)) {
                continue;
            }

            foreach ($colors as $color) {
                if (! isset($color['name'], $color['hex'])) {
                    continue;
                }

                $this->addRow($color['hex'], $color['name'], $listKey);
            }
        }
    }

    private function importColorNameList(): void
    {
        $response = Http::timeout(120)->get(config('color.urls.colornames'));
        $response->throw();

        foreach ($response->json() as $color) {
            if (! isset($color['name'], $color['hex'])) {
                continue;
            }

            $this->addRow($color['hex'], $color['name'], 'color-name-list');
        }
    }

    private function importXkcd(): void
    {
        $response = Http::timeout(60)->get(config('color.urls.xkcd'));
        $response->throw();

        foreach ($response->json()['colors'] ?? [] as $color) {
            if (! isset($color['color'], $color['hex'])) {
                continue;
            }

            $this->addRow($color['hex'], $color['color'], 'xkcd');
        }
    }

    private function importResene(): void
    {
        $response = Http::timeout(60)->get(config('color.urls.resene'));
        $response->throw();

        foreach (preg_split('/\r\n|\n|\r/', $response->body()) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '!') || str_starts_with($line, '"')) {
                continue;
            }

            if (! preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
                continue;
            }

            $hex = sprintf('%02X%02X%02X', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
            $name = ucwords(str_replace(['_', '-'], ' ', trim($matches[4])));

            $this->addRow($hex, $name, 'resene');
        }
    }

    private function addRow(string $hex, string $name, string $source): void
    {
        if (! SourcePriority::isEnglishSource($source)) {
            return;
        }

        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return;
        }

        try {
            $hex = ColorMath::normalizeHex($hex);
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->rows[] = [
            'hex' => $hex,
            'name' => $name,
            'source' => $source,
            'source_priority' => SourcePriority::forSource($source),
        ];
    }
}
