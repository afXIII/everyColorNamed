<?php

namespace App\Services\Color;

class DataPaths
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    public static function make(): self
    {
        return new self(config('color.data_path'));
    }

    public function base(): string
    {
        return $this->basePath;
    }

    public function seeds(): string
    {
        return $this->join('seeds');
    }

    public function mergedSeeds(): string
    {
        return $this->join('seeds', 'merged.json');
    }

    public function wordBanks(): string
    {
        return $this->join('seeds', 'word_banks.json');
    }

    public function uniqueLexicon(): string
    {
        return $this->join('seeds', 'unique_lexicon.json');
    }

    public function seedsByHexIndex(): string
    {
        return $this->join('seeds', 'seeds_by_hex.sqlite');
    }

    public function rawSeeds(): string
    {
        return $this->join('seeds', 'raw');
    }

    public function joinRawRows(): string
    {
        return $this->join('seeds', 'raw', 'rows.json');
    }

    public function joinRawManifest(): string
    {
        return $this->join('seeds', 'raw', 'import_manifest.json');
    }

    public function builds(): string
    {
        return $this->join('builds');
    }

    public function build(string $buildId): string
    {
        return $this->join('builds', $buildId);
    }

    public function releases(): string
    {
        return $this->join('releases');
    }

    public function release(string $version): string
    {
        return $this->join('releases', 'v'.$version);
    }

    public function currentPointerFile(): string
    {
        return $this->base().DIRECTORY_SEPARATOR.'current';
    }

    public function currentPointer(): string
    {
        $file = $this->currentPointerFile();
        if (! is_file($file)) {
            throw new \RuntimeException('No current catalog pointer found at '.$file);
        }

        return trim((string) file_get_contents($file));
    }

    public function writeCurrentPointer(string $pointer): void
    {
        file_put_contents($this->currentPointerFile(), $pointer.PHP_EOL);
    }

    public function catalogRoot(?string $pointer = null): string
    {
        $pointer ??= $this->currentPointer();

        if (preg_match('/^releases\/v(\d+)$/', $pointer, $matches) === 1) {
            return $this->release($matches[1]);
        }

        return $this->build($pointer);
    }

    public function catalogRootForVersion(string $version): string
    {
        return $this->release(ltrim($version, 'v'));
    }

    public function ensureDirectory(string ...$parts): string
    {
        $path = $this->join(...$parts);
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    private function join(string ...$parts): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);
    }
}
