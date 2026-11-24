<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/**
 * Catalog endpoints — the first API slice.
 *
 * WHY the ordering matters: "filters" must be declared before {slug},
 * otherwise Laravel would treat /api/products/filters as a product slug.
 */
Route::prefix('products')->group(function (): void {
    Route::get('filters', [ProductController::class, 'filters']);
    Route::get('/', [ProductController::class, 'index']);
    Route::get('{slug}', [ProductController::class, 'show'])->where('slug', '[A-Za-z0-9-]+');
});
