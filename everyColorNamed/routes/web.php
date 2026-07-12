<?php

use Illuminate\Support\Facades\Route;

/*
| SPA shell (Nuxt generate output lands in public/index.html + public/_nuxt/).
| API stays on /api/* via routes/api.php. Health check stays on /up.
*/
Route::get('/{path?}', function () {
    $index = public_path('index.html');

    if (! is_file($index)) {
        return response(
            'Frontend not built. Run the Cloud build script (or: cd web && NUXT_PUBLIC_API_BASE=/api npm run generate).',
            503,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    return response(file_get_contents($index), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache',
    ]);
})->where('path', '^(?!api(?:/|$)|up$).*');
