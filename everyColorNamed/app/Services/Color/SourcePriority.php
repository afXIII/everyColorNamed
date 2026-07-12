<?php

namespace App\Services\Color;

class SourcePriority
{
    public static function forSource(string $source): int
    {
        $map = config('color.source_priority', []);
        $key = self::normalizeKey($source);

        if (isset($map[$key])) {
            return $map[$key];
        }

        foreach ($map as $name => $priority) {
            if (str_contains($key, $name)) {
                return $priority;
            }
        }

        return config('color.default_source_priority', 75);
    }

    public static function displayName(string $sourceKey): string
    {
        return match ($sourceKey) {
            'html' => 'CSS/HTML',
            'x11' => 'X11',
            'ntc' => 'NTC',
            'xkcd' => 'XKCD',
            'wikipedia' => 'Wikipedia',
            'color-name-list' => 'color-name-list',
            'resene' => 'Resene',
            'mlmc_english' => 'MLMC English',
            default => ucfirst($sourceKey),
        };
    }

    public static function isEnglishSource(string $source): bool
    {
        $key = self::normalizeKey($source);
        $skipLists = array_map(self::normalizeKey(...), config('color.skip_lists', []));

        if (in_array($key, $skipLists, true)) {
            return false;
        }

        if (str_starts_with($key, 'mlmc_') && $key !== 'mlmc_english') {
            return false;
        }

        return true;
    }

    private static function normalizeKey(string $source): string
    {
        return strtolower(trim($source));
    }
}
