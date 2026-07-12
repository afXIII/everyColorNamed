<?php

namespace App\Http\Controllers;

use App\Services\Color\ColorId;
use App\Services\Color\DataPaths;
use App\Services\Color\SeedHexIndex;
use App\Services\Color\SourcePriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDO;

class ColorCatalogController extends Controller
{
    public function __construct(
        private readonly DataPaths $paths,
        private readonly SeedHexIndex $seedHexIndex,
    ) {}

    public function manifest(Request $request): JsonResponse
    {
        $catalogRoot = $this->catalogRoot($request);
        $manifestPath = $catalogRoot.DIRECTORY_SEPARATOR.'manifest.json';

        abort_unless(is_file($manifestPath), 404, 'No catalog build found. Run colors:generate-catalog.');

        return response()->json(json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR));
    }

    public function window(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->integer('offset', 0));
        $from = $request->has('from') ? (int) $request->integer('from') : null;
        $limit = min((int) $request->integer('limit', 80), 200);

        $catalogRoot = $this->catalogRoot($request);
        $browsePath = $catalogRoot.DIRECTORY_SEPARATOR.'browse.sqlite';

        abort_unless(is_file($browsePath), 404, 'Browse index missing for this build.');

        $pdo = new PDO('sqlite:'.$browsePath);

        if ($from !== null) {
            $statement = $pdo->prepare(
                'SELECT row_num - 1 AS offset, color_id, hex, name, hue_bucket, text_contrast, r, g, b
                 FROM browse_rows WHERE color_id >= :from ORDER BY color_id ASC LIMIT :limit'
            );
            $statement->bindValue(':from', $from, PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $statement = $pdo->prepare(
                'SELECT row_num - 1 AS offset, color_id, hex, name, hue_bucket, text_contrast, r, g, b
                 FROM browse_rows ORDER BY row_num ASC LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return response()->json([
            'offset' => $offset,
            'from' => $from,
            'limit' => $limit,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, string $hex): JsonResponse
    {
        $hex = strtoupper(ltrim($hex, '#'));
        $colorId = ColorId::fromHex($hex);
        $catalogRoot = $this->catalogRoot($request);
        $shardFile = $catalogRoot.DIRECTORY_SEPARATOR.'shards'.DIRECTORY_SEPARATOR.ColorId::shardKey($colorId).'.sqlite';

        abort_unless(is_file($shardFile), 404);

        $pdo = new PDO('sqlite:'.$shardFile);
        $statement = $pdo->prepare('SELECT * FROM colors WHERE hex = :hex LIMIT 1');
        $statement->execute(['hex' => $hex]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        abort_if($row === false, 404);

        $seed = $this->seedHexIndex->findExact($hex);
        if ($seed !== null) {
            $seed = $this->englishOnlySeed($seed);
        }

        return response()->json([
            'color' => $row,
            'seed' => $seed,
        ]);
    }

    private function catalogRoot(Request $request): string
    {
        $version = $request->query('v');
        if ($version !== null && $version !== '') {
            $releasePath = $this->paths->catalogRootForVersion((string) $version);
            abort_unless(is_dir($releasePath), 404, 'Release v'.$version.' not found.');

            return $releasePath;
        }

        return $this->paths->catalogRoot();
    }

    /** @param  array<string, mixed>  $seed */
    private function englishOnlySeed(array $seed): array
    {
        $seed['aliases'] = array_values(array_filter(
            $seed['aliases'] ?? [],
            fn (array $alias): bool => SourcePriority::isEnglishSource($alias['source_key'] ?? $alias['source'] ?? ''),
        ));

        $seed['conflicting_names'] = array_values(array_filter(
            $seed['conflicting_names'] ?? [],
            fn (array $conflict): bool => SourcePriority::isEnglishSource($conflict['source_key'] ?? $conflict['source'] ?? ''),
        ));

        return $seed;
    }
}
