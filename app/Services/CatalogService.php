<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * All catalog read logic lives here.
 *
 * Invariants owned by this class:
 *  1. Only active products are visible.
 *  2. A "purchasable" offer is active AND has at least one available key.
 *  3. Prices are always read from persisted offers, never from request input.
 */
class CatalogService
{
    /**
     * Backend-derived values for the store's filter controls.
     *
     * WHY: the SPA must not hardcode platforms/regions/price bounds. Only
     * purchasable offers contribute, so selecting an option never yields an
     * empty catalog due to a stale or sold-out filter value.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $offers = $this->purchasableOffersQuery();

        // Platforms come from purchasable offers too, so every option leads
        // to at least one result instead of a possible empty catalog.
        $platforms = (clone $offers)
            ->distinct()
            ->orderBy('platform')
            ->pluck('products.platform')
            ->all();

        $regions = (clone $offers)
            ->distinct()
            ->orderBy('region')
            ->pluck('offers.region')
            ->all();

        $bounds = (clone $offers)
            ->selectRaw('MIN(offers.price_cents) AS min_cents, MAX(offers.price_cents) AS max_cents')
            ->first();

        return [
            'platforms' => $platforms,
            'regions' => $regions,
            'price_range' => [
                'min_cents' => $bounds?->min_cents !== null ? (int) $bounds->min_cents : null,
                'max_cents' => $bounds?->max_cents !== null ? (int) $bounds->max_cents : null,
            ],
            'currency' => config('catalog.currency'),
        ];
    }

    /**
     * Build the paginated catalog plus the normalized applied filters.
     *
     * The SQL uses a derived table because price filters must compare against
     * the computed min_price_cents alias, which cannot be used in a WHERE
     * clause of the same SELECT in PostgreSQL/SQLite.
     *
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, applied_filters: array<string, mixed>}
     */
    public function products(array $filters): array
    {
        $base = Product::query();
        $base->where('products.is_active', true);
        $this->addComputedPriceColumns($base);

        if (! empty($filters['platform'])) {
            // Case-insensitive exact match against an API-supplied option.
            $base->whereRaw('LOWER(products.platform) = LOWER(?)', [$filters['platform']]);
        }

        if (! empty($filters['region'])) {
            $base->whereExists(function (Builder $query) use ($filters): void {
                $query->select(DB::raw(1))
                    ->from('offers')
                    ->whereColumn('offers.product_id', 'products.id')
                    ->where('offers.is_active', true)
                    ->whereRaw('LOWER(offers.region) = LOWER(?)', [$filters['region']])
                    ->whereExists(function (Builder $keys): void {
                        $keys->select(DB::raw(1))
                            ->from('digital_keys')
                            ->whereColumn('digital_keys.offer_id', 'offers.id')
                            ->where('digital_keys.status', 'available');
                    });
            });
        }

        // Wrap the Eloquent query so computed columns become ordinary columns.
        $query = DB::query()->fromSub($base, 'products');

        if (array_key_exists('min_price', $filters) && $filters['min_price'] !== null) {
            $query->where('min_price_cents', '>=', (int) $filters['min_price']);
        }

        if (array_key_exists('max_price', $filters) && $filters['max_price'] !== null) {
            $query->where('min_price_cents', '<=', (int) $filters['max_price']);
        }

        $sort = $filters['sort'] ?? 'popularity';
        $this->applySort($query, $sort);

        $perPage = (int) ($filters['per_page'] ?? config('catalog.per_page'));
        $page = (int) ($filters['page'] ?? 1);

        // paginate() validates nothing destructive; it only reads rows.
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [
            'platform' => $filters['platform'] ?? null,
            'region' => $filters['region'] ?? null,
            'min_price_cents' => isset($filters['min_price']) ? (int) $filters['min_price'] : null,
            'max_price_cents' => isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ];

        return ['paginator' => $paginator, 'applied_filters' => $appliedFilters];
    }

    /**
     * Fetch one active product with its offers for the detail endpoint.
     *
     * @throws ApiException when the slug is missing or the product is inactive
     */
    public function findBySlug(string $slug): Product
    {
        $product = Product::query()
            ->where('products.slug', $slug)
            ->where('products.is_active', true)
            ->with(['offers' => function ($query): void {
                // Inactive offers stay visible with available: false.
                $query->where('offers.is_active', true)
                    ->with('seller')
                    ->withCount(['digitalKeys as available_key_count' => function ($keys): void {
                        $keys->where('digital_keys.status', 'available');
                    }])
                    ->orderBy('offers.price_cents')
                    ->orderBy('offers.region');
            }])
            ->first();

        if ($product === null) {
            throw ApiException::productNotFound();
        }

        // WHY: unlike the list query, this returns a plain Product model, so
        // the computed columns (min_price_cents, available_offer_count) do not
        // exist. Derive both from the loaded offers so list and detail agree.
        // Without this, a product could report available: true while showing
        // min_price_cents: null. Offers are already ordered by price, so the
        // first purchasable one is the cheapest and matches the list rule.
        $purchasableOffers = $product->offers
            ->filter(fn ($offer): bool => (int) $offer->available_key_count > 0);

        $product->setAttribute('available_offer_count', $purchasableOffers->count());
        $product->setAttribute(
            'min_price_cents',
            $purchasableOffers->isNotEmpty()
                ? (int) $purchasableOffers->first()->price_cents
                : null,
        );

        return $product;
    }

    /**
     * Active product summary for homepage blocks.
     *
     * WHY: banner configuration can point at a missing or inactive slug.
     * Returning null lets the caller degrade gracefully instead of throwing
     * PRODUCT_NOT_FOUND and breaking the whole homepage. Offers are
     * intentionally not loaded, so ProductResource returns a catalog summary
     * rather than the product detail payload.
     */
    public function findActiveSummaryBySlug(string $slug): ?Product
    {
        $query = Product::query()
            ->where('products.slug', $slug)
            ->where('products.is_active', true);

        $this->addComputedPriceColumns($query);

        return $query->first();
    }

    /**
     * Query builder for offers that a buyer could actually purchase.
     */
    private function purchasableOffersQuery(): Builder
    {
        return DB::table('offers')
            ->join('products', 'products.id', '=', 'offers.product_id')
            ->where('products.is_active', true)
            ->where('offers.is_active', true)
            ->whereExists(function (Builder $keys): void {
                $keys->select(DB::raw(1))
                    ->from('digital_keys')
                    ->whereColumn('digital_keys.offer_id', 'offers.id')
                    ->where('digital_keys.status', 'available');
            });
    }

    /**
     * Attach the two computed columns used by the catalog list.
     *
     * WHY: both subqueries correlate to products.id and count only offers
     * that are active and still have an unassigned key. This is public
     * because SearchService returns the same product DTO and must apply the
     * identical pricing rule — there is only one definition of "purchasable".
     */
    public function addComputedPriceColumns(EloquentBuilder $query): void
    {
        $query->select('products.*')
            ->selectSub($this->availableOfferSubquery('MIN(offers.price_cents)'), 'min_price_cents')
            ->selectSub($this->availableOfferSubquery('COUNT(*)'), 'available_offer_count');
    }

    /**
     * The $expression argument is always an internal literal (MIN or COUNT),
     * never user input, so selectRaw() is safe here.
     */
    private function availableOfferSubquery(string $expression): Builder
    {
        return DB::table('offers')
            ->selectRaw($expression)
            ->whereColumn('offers.product_id', 'products.id')
            ->where('offers.is_active', true)
            ->whereExists(function (Builder $keys): void {
                $keys->select(DB::raw(1))
                    ->from('digital_keys')
                    ->whereColumn('digital_keys.offer_id', 'offers.id')
                    ->where('digital_keys.status', 'available');
            });
    }

    /**
     * Apply one of the four documented sorts.
     *
     * WHY: unavailable products have NULL min_price_cents. CASE puts them
     * last for both directions instead of letting the database choose.
     */
    private function applySort(Builder $query, string $sort): void
    {
        $nullsLast = 'CASE WHEN min_price_cents IS NULL THEN 1 ELSE 0 END ASC';

        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw($nullsLast)->orderBy('min_price_cents')->orderBy('slug');
                break;
            case 'price_desc':
                $query->orderByRaw($nullsLast)->orderByDesc('min_price_cents')->orderBy('slug');
                break;
            case 'popularity':
            case 'relevance':
                // Relevance needs a search term; catalog has none, so it
                // falls back to popularity. Real similarity ranking is the
                // separate /search endpoint in a later slice.
                $query->orderByDesc('popularity')->orderBy('slug');
                break;
        }
    }
}
