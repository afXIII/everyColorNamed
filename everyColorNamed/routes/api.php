<?php

use App\Http\Controllers\ColorCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest', [ColorCatalogController::class, 'manifest']);
Route::get('/colors/window', [ColorCatalogController::class, 'window']);
Route::get('/colors/{hex}', [ColorCatalogController::class, 'show'])->where('hex', '[0-9A-Fa-f]{6}');
