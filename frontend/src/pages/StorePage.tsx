import { useSearchParams } from 'react-router-dom'
import { getProductFilters, getProducts } from '../api/endpoints'
import type { ProductQuery } from '../api/endpoints'
import CatalogFilters from '../components/catalog/CatalogFilters'
import ProductCard from '../components/catalog/ProductCard'
import EmptyState from '../components/ui/EmptyState'
import ErrorState from '../components/ui/ErrorState'
import Pagination from '../components/ui/Pagination'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'
import type { SortOption } from '../types/api'

const validSorts: SortOption[] = ['price_asc', 'price_desc', 'popularity', 'relevance']

function parseSort(value: string | null): SortOption {
    return validSorts.find((sort) => sort === value) ?? 'popularity'
}

function parsePositiveInt(value: string | null, fallback: number): number {
    const parsed = Number(value)

    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}

function queryFromParams(params: URLSearchParams): ProductQuery {
    const minPrice = params.get('min_price')
    const maxPrice = params.get('max_price')

    return {
        platform: params.get('platform') || null,
        region: params.get('region') || null,
        min_price: minPrice === null ? null : parsePositiveInt(minPrice, 0),
        max_price: maxPrice === null ? null : parsePositiveInt(maxPrice, 0),
        sort: parseSort(params.get('sort')),
        page: parsePositiveInt(params.get('page'), 1),
        per_page: parsePositiveInt(params.get('per_page'), 12),
    }
}

function paramsFromQuery(query: ProductQuery): URLSearchParams {
    const params = new URLSearchParams()

    if (query.platform) {
        params.set('platform', query.platform)
    }
    if (query.region) {
        params.set('region', query.region)
    }
    if (query.min_price !== null && query.min_price !== undefined) {
        params.set('min_price', String(query.min_price))
    }
    if (query.max_price !== null && query.max_price !== undefined) {
        params.set('max_price', String(query.max_price))
    }
    if (query.sort && query.sort !== 'popularity') {
        params.set('sort', query.sort)
    }
    if (query.page && query.page !== 1) {
        params.set('page', String(query.page))
    }
    if (query.per_page && query.per_page !== 12) {
        params.set('per_page', String(query.per_page))
    }

    return params
}

export default function StorePage() {
    const [params, setParams] = useSearchParams()
    const query = queryFromParams(params)
    const queryKey = params.toString()

    const filters = useAsync(() => getProductFilters(), [])
    const products = useAsync(() => getProducts(query), [queryKey])
    const visibleProducts = (products.data?.data ?? []).filter((product) => product.available)

    function applyPatch(patch: ProductQuery): void {
        setParams(paramsFromQuery({ ...query, ...patch }), { replace: false })
    }

    function hrefForPage(page: number): string {
        const next = paramsFromQuery(query)
        next.set('page', String(page))

        return `/products?${next.toString()}`
    }

    return (
        <div className="store-page">
            <header className="page-header">
                <p className="eyebrow">Catalog</p>
                <h1>All games</h1>
                <p className="muted">Browse offers by category, platform, region, and price.</p>
            </header>

            {filters.loading && <Spinner label="Loading filters" />}
            {filters.error && (
                <ErrorState message={filters.error.message} onRetry={filters.reload} />
            )}

            <CatalogFilters
                filters={filters.data?.data ?? null}
                value={query}
                onChange={applyPatch}
            />

            {products.loading && <Spinner label="Loading products" />}
            {products.error && (
                <ErrorState message={products.error.message} onRetry={products.reload} />
            )}

            {products.data && visibleProducts.length === 0 && (
                <EmptyState
                    title="No games found"
                    message="Try changing the platform, region, or price range."
                    action={{
                        label: 'Clear filters',
                        onClick: () =>
                            applyPatch({
                                platform: null,
                                region: null,
                                min_price: null,
                                max_price: null,
                                sort: 'popularity',
                                page: 1,
                            }),
                    }}
                />
            )}

            {products.data && visibleProducts.length > 0 && (
                <>
                    <p className="result-count" aria-live="polite">
                        {products.data.meta.pagination.total} games
                    </p>
                    <div className="product-grid">
                        {visibleProducts.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                    <Pagination meta={products.data.meta.pagination} hrefForPage={hrefForPage} />
                </>
            )}
        </div>
    )
}
