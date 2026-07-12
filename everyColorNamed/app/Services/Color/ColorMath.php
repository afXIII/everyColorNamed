<?php

namespace App\Services\Color;

class ColorMath
{
    public static function normalizeHex(string $hex): string
    {
        $hex = ltrim(strtoupper(trim($hex)), '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            throw new \InvalidArgumentException("Invalid hex color: {$hex}");
        }

        return $hex;
    }

    public static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d < 0.00001) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        if ($max === $r) {
            $h = (($g - $b) / $d) + ($g < $b ? 6 : 0);
        } elseif ($max === $g) {
            $h = (($b - $r) / $d) + 2;
        } else {
            $h = (($r - $g) / $d) + 4;
        }

        $h *= 60;

        return [$h, $s * 100, $l * 100];
    }

    public static function rgbToLab(int $r, int $g, int $b): array
    {
        $r = self::pivotRgb($r / 255);
        $g = self::pivotRgb($g / 255);
        $b = self::pivotRgb($b / 255);

        $x = ($r * 0.4124564 + $g * 0.3575761 + $b * 0.1804375) / 0.95047;
        $y = ($r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750) / 1.00000;
        $z = ($r * 0.0193339 + $g * 0.1191920 + $b * 0.9503041) / 1.08883;

        $x = self::pivotXyz($x);
        $y = self::pivotXyz($y);
        $z = self::pivotXyz($z);

        return [
            (116 * $y) - 16,
            500 * ($x - $y),
            200 * ($y - $z),
        ];
    }

    public static function deltaE(array $lab1, array $lab2): float
    {
        return sqrt(
            ($lab1[0] - $lab2[0]) ** 2 +
            ($lab1[1] - $lab2[1]) ** 2 +
            ($lab1[2] - $lab2[2]) ** 2
        );
    }

    public static function textContrast(int $r, int $g, int $b): string
    {
        $luminance = self::relativeLuminance($r, $g, $b);

        return $luminance > 0.179 ? 'dark' : 'light';
    }

    public static function hueBucket(int $r, int $g, int $b): string
    {
        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        // Neutrals require low saturation. Dark reds/blues stay chromatic —
        // Black/Gray/White are narrow bands; rainbow buckets get everything else.
        if ($s < 12 && $l >= 96) {
            return 'White';
        }

        if ($s < 12 && $l <= 7) {
            return 'Black';
        }

        if ($s < 12) {
            return 'Gray';
        }

        // Brown: red-orange-yellow hues that are dark or muted. Must be
        // checked before the chromatic buckets or it is unreachable.
        if ($h >= 15 && $h < 55 && ($l < 35 || ($s < 45 && $l < 60))) {
            return 'Brown';
        }

        if ($h < 15 || $h >= 345) {
            return 'Red';
        }

        if ($h < 45) {
            return 'Orange';
        }

        if ($h < 70) {
            return 'Yellow';
        }

        if ($h < 160) {
            return 'Green';
        }

        if ($h < 200) {
            return 'Cyan';
        }

        if ($h < 255) {
            return 'Blue';
        }

        if ($h < 290) {
            return 'Purple';
        }

        return 'Pink';
    }

    /**
     * Monotonic sort key for perceptual browse ordering: groups rows by nav
     * bucket, then orders within the bucket (neutrals by lightness, chromatic
     * buckets by hue → lightness → saturation). Fits in a signed 64-bit int.
     */
    public static function browseSortKey(int $r, int $g, int $b, int $bucketIndex): int
    {
        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        $hueKey = (int) round($h * 100);        // 0..36000
        $satKey = (int) round($s * 100);        // 0..10000
        $lightKey = (int) round($l * 100);      // 0..10000

        $bucket = self::hueBucket($r, $g, $b);
        $isNeutral = in_array($bucket, ['Black', 'Gray', 'White', 'Brown'], true);

        [$p1, $p2, $p3] = $isNeutral
            ? [$lightKey, $hueKey, $satKey]
            : [$hueKey, $lightKey, $satKey];

        return $bucketIndex * 10_000_000_000_000_000
            + $p1 * 100_000_000_000
            + $p2 * 100_000
            + $p3;
    }

    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $channels = [$r, $g, $b];
        $linear = array_map(function (int $channel): float {
            $v = $channel / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private static function pivotRgb(float $value): float
    {
        return $value > 0.04045
            ? (($value + 0.055) / 1.055) ** 2.4
            : $value / 12.92;
    }

    private static function pivotXyz(float $value): float
    {
        return $value > 0.008856 ? $value ** (1 / 3) : (7.787 * $value) + (16 / 116);
    }
}
