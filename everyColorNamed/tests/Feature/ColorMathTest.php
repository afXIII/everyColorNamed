<?php

use App\Services\Color\ColorId;
use App\Services\Color\ColorMath;

it('packs and unpacks color ids', function () {
    expect(ColorId::fromRgb(255, 99, 71))->toBe(0xFF6347);
    expect(ColorId::toHex(0xFF6347))->toBe('FF6347');
    expect(ColorId::toRgb(0xFF6347))->toBe([255, 99, 71]);
    expect(ColorId::shardKey(0xFF6347))->toBe('rff');
});

it('normalizes hex values', function () {
    expect(ColorMath::normalizeHex('#ff6347'))->toBe('FF6347');
});

it('keeps saturated dark colors in chromatic buckets', function () {
    expect(ColorMath::hueBucket(40, 0, 1))->toBe('Red');
    expect(ColorMath::hueBucket(32, 8, 8))->toBe('Red');
    expect(ColorMath::hueBucket(0, 0, 40))->toBe('Blue');
    expect(ColorMath::hueBucket(5, 5, 5))->toBe('Black');
    expect(ColorMath::hueBucket(20, 20, 20))->toBe('Gray');
    expect(ColorMath::hueBucket(128, 128, 128))->toBe('Gray');
});
