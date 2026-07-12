<?php

return [
    'data_path' => env('COLOR_DATA_PATH', dirname(base_path()).DIRECTORY_SEPARATOR.'data'),

    'urls' => [
        'colorlists' => 'https://unpkg.com/color-name-lists@latest/dist/colorlists.json',
        'colornames' => 'https://unpkg.com/color-name-list@latest/dist/colornames.json',
        'xkcd' => 'https://raw.githubusercontent.com/dariusk/corpora/master/data/colors/xkcd.json',
        'resene' => 'https://people.csail.mit.edu/jaffer/Color/resenecolours.txt',
    ],

    'skip_lists' => [
        'risograph',
        // Non-English language lists from color-name-lists
        'french',
        'spanish',
        'german',
        'hindi',
        'italian',
        'portuguese',
        'dutch',
        'russian',
        'polish',
        'swedish',
        'finnish',
        'romanian',
        'persian',
        'chineseTraditional',
        'japaneseTraditional',
        'mlmc_chinese',
        'mlmc_french',
        'mlmc_german',
        'mlmc_spanish',
        'mlmc_korean',
        'mlmc_persian',
        'mlmc_portuguese',
        'mlmc_russian',
        'mlmc_dutch',
        'mlmc_swedish',
        'mlmc_polish',
        'mlmc_finnish',
        'mlmc_romanian',
    ],

    'source_priority' => [
        'html' => 10,
        'css' => 10,
        'x11' => 20,
        'ntc' => 30,
        'wikipedia' => 35,
        'xkcd' => 40,
        'osxcrayons' => 45,
        'windows' => 45,
        'nbsIscc' => 50,
        'ral' => 50,
        'ridgway' => 50,
        'werner' => 50,
        'sanzoWadaI' => 50,
        'leCorbusier' => 50,
        'japaneseTraditional' => 50,
        'chineseTraditional' => 50,
        'resene' => 55,
        'color-name-list' => 70,
        'thesaurus' => 80,
        'basic' => 90,
    ],

    'default_source_priority' => 75,

    'catalog_levels' => [
        0 => ['step' => null, 'seeds_only' => true],
        1 => ['step' => 128],
        2 => ['step' => 64],
        3 => ['step' => 32],
        4 => ['step' => 16],
        5 => ['step' => 8],
        6 => ['step' => 4],
        7 => ['step' => 2],
        8 => ['step' => 1, 'filter' => 'even_color_id'],
        9 => ['step' => 1, 'filter' => 'color_id_mod4'],
        10 => ['step' => 1],
    ],

    'prefixes' => [
        'Pale', 'Deep', 'Dusty', 'Bright', 'Soft', 'Smoky', 'Muted', 'Vivid',
        'Dark', 'Light', 'Rich', 'Faded', 'Sharp', 'Gentle', 'Wild', 'Still',
    ],
    'suffixes' => [
        'Haze', 'Glow', 'Mist', 'Stone', 'Drift', 'Flash', 'Dusk', 'Bloom',
        'Shade', 'Tone', 'Wash', 'Veil', 'Spark', 'Tide', 'Field', 'Song',
    ],

    'fallback_tokens' => [
        'Twilight', 'Horizon', 'Ripple', 'Velvet', 'Ember', 'Shimmer', 'Cascade', 'Mirage',
        'Petals', 'Thread', 'Lantern', 'Window', 'Garden', 'Harbor', 'Summit', 'Valley',
        'Ribbon', 'Canvas', 'Feather', 'Pebble', 'Willow', 'Breeze', 'Echo', 'Whisper',
        'Crystal', 'Meadow', 'Canyon', 'Forest', 'River', 'Cloud', 'Aurora', 'Nebula',
        'Quarry', 'Studio', 'Pavilion', 'Terrace', 'Orchard', 'Prairie', 'Glacier', 'Lagoon',
    ],

    // Sidebar / jump-nav display order (Black first — list starts at #000000)
    'nav_order' => [
        'Black', 'Gray', 'Brown', 'White',
        'Red', 'Orange', 'Yellow', 'Green', 'Cyan', 'Blue', 'Purple', 'Pink',
    ],
];
