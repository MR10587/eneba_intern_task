<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/**
 * Homepage endpoint.
 *
 * WHY: one public endpoint returns enabled blocks in configured order. Each
 * block's content is resolved server-side from its own type and DB config, so
 * the SPA only renders what it receives.
 */
Route::get('home', HomeController::class);

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

/**
 * Search endpoint.
 *
 * WHY: /search is a separate read model from /products. It adds relevance
 * ranking and a total match count while reusing the same ProductResource and
 * the same purchasable-price rule as the catalog.
 */
Route::get('search', SearchController::class);

/**
 * Cart endpoints — public for guests, authenticated for returning users.
 *
 * WHY: shoppers may add offers before signing in. CartService derives the cart
 * either from the authenticated user or from a server-issued guest token; a
 * signed-in user never sees a guest cart and a guest token never reaches a
 * user cart. Sign-in merges the guest cart into the user's cart.
 */
Route::prefix('cart')->group(function (): void {
    Route::get('/', [CartController::class, 'index']);
    Route::post('items', [CartController::class, 'store']);
    Route::delete('items/{offer}', [CartController::class, 'destroy'])->whereNumber('offer');
});

/**
 * Checkout and order history — signed-in only.
 *
 * WHY: the whole group sits behind auth:sanctum. CheckoutService owns the
 * payment/key transaction and OrderService scopes every order lookup to the
 * authenticated user, so no client-supplied user or order ownership is trusted.
 */
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('checkout', [CheckoutController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
});
