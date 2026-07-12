<?php

namespace App\Services\Color;

class ColorId
{
    public static function fromRgb(int $r, int $g, int $b): int
    {
        return ($r << 16) | ($g << 8) | $b;
    }

    public static function toRgb(int $colorId): array
    {
        return [
            ($colorId >> 16) & 0xFF,
            ($colorId >> 8) & 0xFF,
            $colorId & 0xFF,
        ];
    }

    public static function toHex(int $colorId): string
    {
        [$r, $g, $b] = self::toRgb($colorId);

        return sprintf('%02X%02X%02X', $r, $g, $b);
    }

    public static function fromHex(string $hex): int
    {
        $hex = ltrim(strtoupper(trim($hex)), '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            throw new \InvalidArgumentException("Invalid hex color: {$hex}");
        }

        return self::fromRgb(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    public static function shardKey(int $colorId): string
    {
        $r = ($colorId >> 16) & 0xFF;

        return sprintf('r%02x', $r);
    }
}
