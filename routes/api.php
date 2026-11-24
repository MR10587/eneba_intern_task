<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/**
 * Authentication endpoints.
 *
 * WHY the split group: register/login must be reachable anonymously, while
 * logout/me need a Sanctum personal access token. `auth:sanctum` failures are
 * rendered as 401 UNAUTHENTICATED by bootstrap/app.php.
 */
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

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
