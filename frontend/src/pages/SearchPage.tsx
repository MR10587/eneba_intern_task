import { Link, useSearchParams } from 'react-router-dom'
import { searchProducts } from '../api/endpoints'
import ProductCard from '../components/catalog/ProductCard'
import EmptyState from '../components/ui/EmptyState'
import ErrorState from '../components/ui/ErrorState'
import Pagination from '../components/ui/Pagination'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'

export default function SearchPage() {
    const [params] = useSearchParams()
    const query = (params.get('q') ?? '').trim()
    const page = Number(params.get('page'))
    const currentPage = Number.isInteger(page) && page > 0 ? page : 1

    const results = useAsync(
        async () => (query.length >= 2 ? searchProducts({ q: query, page: currentPage }) : null),
        [query, currentPage],
    )
    const visibleProducts = (results.data?.data ?? []).filter((product) => product.available)

    function hrefForPage(nextPage: number): string {
        return `/search?q=${encodeURIComponent(query)}&page=${nextPage}`
    }

    return (
        <div className="search-page">
            <header className="page-header">
                <p className="eyebrow">Search</p>
                <h1>{query ? `Results for “${query}”` : 'Search games'}</h1>
                <p className="muted">Search by name and we’ll find close matches for you.</p>
            </header>

            {query.length < 2 && (
                <EmptyState
                    title="Enter a search term"
                    message="Use the search box above with at least two characters."
                />
            )}

            {results.loading && <Spinner label="Searching" />}

            {results.error && (
                <ErrorState message={results.error.message} onRetry={results.reload} />
            )}

            {results.data && (
                <>
                    <p className="result-count" aria-live="polite">
                        {results.data.meta.result_count} results
                    </p>

                    {visibleProducts.length === 0 ? (
                        <EmptyState
                            title="No matches"
                            message="Check the spelling or try a shorter, partial name."
                        >
                            <Link className="btn btn--secondary" to="/products">
                                Browse all games
                            </Link>
                        </EmptyState>
                    ) : (
                        <>
                            <div className="product-grid">
                                {visibleProducts.map((product) => (
                                    <ProductCard key={product.id} product={product} />
                                ))}
                            </div>
                            <Pagination
                                meta={results.data.meta.pagination}
                                hrefForPage={hrefForPage}
                            />
                        </>
                    )}
                </>
            )}
        </div>
    )
}
