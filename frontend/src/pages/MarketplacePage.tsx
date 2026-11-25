import { SlidersHorizontal } from 'lucide-react'
import { useState } from 'react'
import {
    addCartItem,
    getCart,
    getHome,
    getProductBySlug,
    getProductFilters,
    getProducts,
    removeCartItem,
    searchProducts,
} from '../api/endpoints'
import CartDrawer from '../components/cart/CartDrawer'
import FilterSidebar from '../components/catalog/FilterSidebar'
import ProductGrid from '../components/catalog/ProductGrid'
import HeroGrid from '../components/home/HeroGrid'
import Footer from '../components/layout/Footer'
import Navbar from '../components/layout/Navbar'
import { useAuth } from '../context/authContext'
import { useToast } from '../context/toastContext'

import type { Cart, CartMeta, ProductSummary } from '../types/api'
import type { Category } from '../types'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'

interface CartResponse {
    data: Cart
    meta: CartMeta
}

const perPage = 12

export default function MarketplacePage() {
    const { token } = useAuth()
    const { showToast } = useToast()

    const [query, setQuery] = useState('')
    const [category, setCategory] = useState<Category>('Games')
    const [platform, setPlatform] = useState('')
    const [region, setRegion] = useState('')
    const [maxPriceCents, setMaxPriceCents] = useState<number | null>(null)
    const [page, setPage] = useState(1)
    const [cartOpen, setCartOpen] = useState(false)
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
    const [busySlug, setBusySlug] = useState<string | null>(null)

    const home = useAsync(() => getHome(), [])
    const filters = useAsync(() => getProductFilters(), [])

    const catalog = useAsync(async () => {
        const needle = query.trim()

        const filters = {
            category,
            platform: platform || null,
            region: region || null,
            max_price: maxPriceCents,
            page,
            per_page: perPage,
        }

        if (needle.length >= 2) {
            // The search endpoint applies the same filters before pagination,
            // so the grid and its page controls always describe the same set.
            return searchProducts({ q: needle, ...filters })
        }

        return getProducts({
            ...filters,
            sort: 'popularity',
        })
    }, [category, maxPriceCents, page, platform, query, region])

    const cart = useAsync<CartResponse>(() => getCart(), [token])

    const products = (catalog.data?.data ?? []).filter((product) => product.available)
    const pagination = catalog.data?.meta.pagination

    async function addToCart(product: ProductSummary): Promise<void> {
        setBusySlug(product.slug)

        try {
            const detail = await getProductBySlug(product.slug)
            const offer = detail.data.offers.find((item) => item.available)

            if (!offer) {
                showToast('No available offers for this product right now.', { tone: 'error' })

                return
            }

            await addCartItem({ product_id: detail.data.id, offer_id: offer.id })
            cart.reload()
            showToast(`${product.name} added to cart. Ready for checkout.`, {
                action: { label: 'View cart', onClick: () => setCartOpen(true) },
            })
        } catch (caught: unknown) {
            showToast(apiErrorMessage(caught), { tone: 'error' })
        } finally {
            setBusySlug(null)
        }
    }

    async function removeFromCart(offerId: number): Promise<void> {
        try {
            await removeCartItem(offerId)
            cart.reload()
        } catch (caught: unknown) {
            showToast(apiErrorMessage(caught), { tone: 'error' })
        }
    }

    function openCart(): void {
        setCartOpen(true)
    }

    function changeQuery(value: string): void {
        setQuery(value)
        setPage(1)
    }

    function changePlatform(value: string): void {
        setPlatform(value)
        setPage(1)
    }

    function changeRegion(value: string): void {
        setRegion(value)
        setPage(1)
    }

    function changeMaxPrice(value: number): void {
        setMaxPriceCents(value)
        setPage(1)
    }

    function resetFilters(): void {
        setPlatform('')
        setRegion('')
        setMaxPriceCents(null)
        setQuery('')
        setPage(1)
    }

    function selectCategory(next: Category): void {
        setCategory(next)
        setPage(1)
        document
            .getElementById('marketplace')
            ?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    }

    return (
        <div id="top" className="min-h-screen">
            <Navbar
                query={query}
                onQueryChange={changeQuery}
                activeCategory={category}
                onCategoryChange={selectCategory}
                cartCount={cart.data?.meta.item_count ?? 0}
                onOpenCart={openCart}
            />

            <main className="mx-auto max-w-[1280px] px-4 py-6">
                {home.loading && (
                    <p className="mb-3 text-sm text-lavender" role="status">
                        Loading store highlights…
                    </p>
                )}
                {home.error && (
                    <div
                        role="alert"
                        className="mb-3 rounded-xl border border-promo/40 bg-promo/10 px-4 py-3 text-sm text-promo"
                    >
                        {home.error.message}{' '}
                        <button type="button" onClick={home.reload} className="underline">
                            Retry
                        </button>
                    </div>
                )}
                <HeroGrid blocks={home.data?.data ?? []} onSelectCategory={selectCategory} />

                <section id="marketplace" className="mt-8 scroll-mt-36">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-xs font-black tracking-widest text-sun uppercase">
                                Live marketplace
                            </p>
                            <h1 className="text-2xl font-black text-white sm:text-3xl">
                                {category === 'Best Deals' ? 'Top offers' : category}
                            </h1>
                        </div>
                        <button
                            type="button"
                            onClick={() => setMobileFiltersOpen((current) => !current)}
                            aria-expanded={mobileFiltersOpen}
                            className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-royal px-3 py-2 text-sm font-semibold text-white lg:hidden"
                        >
                            <SlidersHorizontal className="h-4 w-4" />
                            Filters
                        </button>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
                        <div className={mobileFiltersOpen ? 'block' : 'hidden lg:block'}>
                            <FilterSidebar
                                filters={filters.data?.data ?? null}
                                selectedPlatform={platform}
                                selectedRegion={region}
                                maxPriceCents={maxPriceCents}
                                onPlatformChange={changePlatform}
                                onRegionChange={changeRegion}
                                onMaxPriceChange={changeMaxPrice}
                                onReset={resetFilters}
                            />
                        </div>

                        <div>
                            <p className="mb-3 text-sm text-lavender" aria-live="polite">
                                {pagination?.total ?? products.length} products
                            </p>

                            {catalog.loading && (
                                <p className="py-16 text-center text-sm text-lavender">
                                    Loading live products…
                                </p>
                            )}

                            {catalog.error && (
                                <div
                                    role="alert"
                                    className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center text-sm text-promo"
                                >
                                    {catalog.error.message}{' '}
                                    <button
                                        type="button"
                                        onClick={catalog.reload}
                                        className="underline"
                                    >
                                        Retry
                                    </button>
                                </div>
                            )}

                            {!catalog.loading && !catalog.error && (
                                <ProductGrid
                                    products={products}
                                    busySlug={busySlug}
                                    onAdd={(product) => void addToCart(product)}
                                />
                            )}

                            {pagination && pagination.last_page > 1 && (
                                <nav
                                    className="mt-5 flex flex-wrap items-center justify-center gap-2"
                                    aria-label="Catalog pages"
                                >
                                    <button
                                        type="button"
                                        disabled={page <= 1}
                                        onClick={() =>
                                            setPage((current) => Math.max(1, current - 1))
                                        }
                                        className="rounded-lg border border-white/10 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                                    >
                                        Previous
                                    </button>
                                    <span className="text-xs text-lavender">
                                        {pagination.from}–{pagination.to} of {pagination.total}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={page >= pagination.last_page}
                                        onClick={() => setPage((current) => current + 1)}
                                        className="rounded-lg border border-white/10 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                                    >
                                        Next
                                    </button>
                                </nav>
                            )}
                        </div>
                    </div>
                </section>
            </main>

            <Footer onCategoryChange={selectCategory} />

            <CartDrawer
                open={cartOpen}
                cart={cart.data}
                loading={cart.loading}
                error={cart.error}
                onClose={() => setCartOpen(false)}
                onRemove={removeFromCart}
                onRetry={cart.reload}
            />
        </div>
    )
}
