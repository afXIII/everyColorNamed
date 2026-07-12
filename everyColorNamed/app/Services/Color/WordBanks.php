<?php

namespace App\Services\Color;

class WordBanks
{
    /** @var array<string, array<string, list<string>>> */
    private array $banks = [];

    /** @var list<string> */
    private array $lexicon = [];

    public function loadFromFile(string $path): void
    {
        $this->banks = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function loadLexicon(string $path): void
    {
        if (! is_file($path)) {
            $this->lexicon = [];

            return;
        }

        $payload = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->lexicon = array_values($payload['words'] ?? []);
    }

    /** @return list<string> */
    public function lexicon(): array
    {
        return $this->lexicon;
    }

    /** @return list<string> */
    public function wordsForBucket(string $hueBucket): array
    {
        $bucket = $this->banks[$hueBucket] ?? $this->banks['Gray'] ?? [];
        $words = [];

        foreach (['materials', 'nature', 'feelings'] as $type) {
            foreach ($bucket[$type] ?? [] as $word) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    public function baseWord(string $hueBucket, int $colorId): string
    {
        $words = $this->wordsForBucket($hueBucket);
        if ($words === []) {
            return 'Color';
        }

        return $words[$colorId % count($words)];
    }

    /** @return list<string> */
    public function allWords(): array
    {
        $words = [];

        foreach (array_keys($this->banks) as $bucket) {
            $words = array_merge($words, $this->wordsForBucket($bucket));
        }

        return array_values(array_unique($words));
    }
}
