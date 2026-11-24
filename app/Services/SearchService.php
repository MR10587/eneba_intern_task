<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Product name search with graceful behaviour across database drivers.
 *
 * Invariants owned by this class:
 *  1. Only active products can be found.
 *  2. PostgreSQL uses pg_trgm similarity so typos such as "mincraft" match
 *     "Minecraft"; SQLite falls back to plain LIKE because it has no trigram
 *     functions. Both paths are parameterised — no user text becomes SQL.
 *  3. Search results carry the same computed price fields as catalog results.
 */
class SearchService
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * @param  array{q: string, page?: int, per_page?: int}  $filters
     * @return array{paginator: LengthAwarePaginator, query: string}
     */
    public function search(array $filters): array
    {
        $term = (string) $filters['q'];
        $driver = DB::connection()->getDriverName();

        $base = Product::query();
        $base->where('products.is_active', true);
        // WHY: reuse CatalogService's definition of purchasable price so a
        // search row and a catalog row for the same product never disagree.
        $this->catalog->addComputedPriceColumns($base);

        // Compute the ranking signal once in the inner query, then filter and
        // sort in the outer query. This avoids relying on ORDER BY surviving
        // inside a derived table and keeps pagination deterministic.
        if ($driver === 'pgsql') {
            $base->selectRaw('similarity(products.name, ?) AS relevance', [$term]);
        } else {
            $base->selectRaw(
                'CASE WHEN LOWER(products.name) LIKE LOWER(?) THEN 1 ELSE 0 END AS prefix_match',
                [$term.'%'],
            );
        }

        // Wrapping the Eloquent query exposes computed columns (prices and
        // relevance) as ordinary columns for the WHERE/ORDER BY below.
        $query = DB::query()->fromSub($base, 'products');

        if ($driver === 'pgsql') {
            $this->applyPostgresSearch($query, $term);
        } else {
            $this->applyFallbackSearch($query, $term);
        }

        $perPage = (int) ($filters['per_page'] ?? config('catalog.per_page'));
        $page = (int) ($filters['page'] ?? 1);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return ['paginator' => $paginator, 'query' => $term];
    }

    /**
     * Rank by trigram similarity, with a substring match as a second way in.
     *
     * WHY: "Minec" has only ~0.45 similarity but still matches via substring,
     * while "mincraft" has ~0.58 similarity and no substring hit. Using OR
     * covers both cases; the GIN trigram index backs the similarity check.
     */
    private function applyPostgresSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $matches) use ($term): void {
            $matches->whereRaw('LOWER(products.name) LIKE LOWER(?)', ['%'.$term.'%'])
                ->orWhere('products.relevance', '>', 0.3);
        })
            // Exact and near-exact names score highest; popularity breaks ties.
            ->orderByDesc('products.relevance')
            ->orderByDesc('products.popularity')
            ->orderBy('products.slug');
    }

    /**
     * Portable fallback for the SQLite test database.
     *
     * WHY: SQLite has no similarity() function, so typo tolerance is not
     * available here. Exact-prefix matches are still ranked above other
     * substring matches so ordering stays deterministic.
     */
    private function applyFallbackSearch(Builder $query, string $term): void
    {
        $query->whereRaw('LOWER(products.name) LIKE LOWER(?)', ['%'.$term.'%'])
            ->orderByDesc('products.prefix_match')
            ->orderByDesc('products.popularity')
            ->orderBy('products.slug');
    }
}
