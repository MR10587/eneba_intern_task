import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type {
    BannerBlockData,
    Cart,
    CartMeta,
    PaginationMeta,
    ProductDetail,
    ProductSummary,
} from '../types/api'
import {
    bannerBlock,
    cartResponse,
    gridBlock,
    jsonResponse,
    offer,
    product,
    productDetail,
} from '../test/fixtures'
import { renderWithAuth } from '../test/renderAuth'
import { isBannerBlockData, isProductSummaryArray } from '../types/guards'
import MarketplacePage from './MarketplacePage'

const minecraft = product()
const cyberpunk = product({
    id: 2,
    slug: 'cyberpunk-2077',
    name: 'Cyberpunk 2077',
    genre: 'RPG',
    min_price_cents: 3999,
    compare_at_price_cents: 4999,
    discount_percent: 20,
    available_offer_count: 3,
})
const hardware = product({
    id: 3,
    slug: 'wireless-controller',
    name: 'Wireless Controller',
    platform: 'Hardware',
    category: 'Hardware',
    genre: 'Accessory',
    min_price_cents: 5999,
    compare_at_price_cents: null,
    discount_percent: null,
})

function pagination(total: number): PaginationMeta {
    return {
        current_page: 1,
        per_page: 12,
        total,
        last_page: 1,
        from: total > 0 ? 1 : null,
        to: total > 0 ? total : null,
    }
}

interface MockApiOptions {
    catalog?: ProductSummary[]
    searchResults?: ProductSummary[]
    detail?: ProductDetail
    cart?: { data: Cart; meta: CartMeta }
}

function mockApi(options: MockApiOptions = {}) {
    const searchResults = options.searchResults ?? [cyberpunk]
    const detail = options.detail ?? productDetail()
    const cart = options.cart ?? cartResponse()

    const fetchMock = vi.fn(async (input: string | URL | Request) => {
        const url = typeof input === 'string' ? input : input.toString()
        const params = new URL(url, 'http://localhost').searchParams
        const catalog =
            params.get('category') === 'Hardware'
                ? [hardware]
                : (options.catalog ?? [minecraft, cyberpunk])

        if (url.startsWith('/api/home')) {
            return jsonResponse({
                data: [
                    bannerBlock({
                        data: {
                            headline: 'Featured this week',
                            subheadline: 'Instant keys, ready to play',
                            cta_label: 'View deal',
                            cta_href: '/products/minecraft',
                            product: minecraft,
                        } satisfies BannerBlockData,
                    }),
                    gridBlock(),
                ],
                meta: { block_count: 2 },
            })
        }

        if (url.startsWith('/api/products/filters')) {
            return jsonResponse({
                data: {
                    categories: ['Games', 'Gift Cards', 'Top-Ups', 'Hardware', 'Best Deals'],
                    platforms: ['Steam', 'Epic Games', 'PlayStation', 'Xbox'],
                    regions: ['Europe', 'Global', 'US'],
                    price_range: { min_cents: 0, max_cents: 50000 },
                    currency: 'EUR',
                },
            })
        }

        if (url.startsWith('/api/search')) {
            return jsonResponse({
                data: searchResults,
                meta: {
                    query: 'cyber',
                    result_count: searchResults.length,
                    applied_filters: {
                        category: params.get('category'),
                        platform: params.get('platform'),
                        region: params.get('region'),
                        min_price_cents: null,
                        max_price_cents: params.has('max_price')
                            ? Number(params.get('max_price'))
                            : null,
                        sort: 'relevance',
                        page: Number(params.get('page') ?? 1),
                        per_page: Number(params.get('per_page') ?? 12),
                    },
                    pagination: pagination(searchResults.length),
                },
            })
        }

        if (url.startsWith('/api/products/')) {
            return jsonResponse({ data: detail, meta: { available_offer_count: 1 } })
        }

        if (url.startsWith('/api/products')) {
            return jsonResponse({
                data: catalog,
                meta: {
                    pagination: pagination(catalog.length),
                    applied_filters: {
                        category: params.get('category'),
                        platform: null,
                        region: null,
                        min_price_cents: null,
                        max_price_cents: null,
                        sort: 'popularity',
                        page: 1,
                        per_page: 12,
                    },
                },
            })
        }

        if (url.startsWith('/api/cart')) {
            return jsonResponse(cart)
        }

        return jsonResponse({ message: 'Unexpected request', code: 'NOT_FOUND' }, 404)
    })

    vi.stubGlobal('fetch', fetchMock)

    return fetchMock
}

describe('live Eneba marketplace', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('renders backend highlights, live products, and no wishlist control', async () => {
        mockApi()

        renderWithAuth(<MarketplacePage />)

        expect(await screen.findByRole('heading', { name: 'Featured this week' })).toBeVisible()
        expect(await screen.findByRole('heading', { name: 'Minecraft' })).toBeVisible()
        expect(screen.getByRole('navigation', { name: 'Categories' })).toBeVisible()
        expect(screen.getByText('2 products')).toBeVisible()
        expect(screen.queryByRole('button', { name: /wishlist/i })).not.toBeInTheDocument()

        const banner = screen
            .getByRole('heading', { name: 'Featured this week' })
            .closest('article')
        expect(banner).not.toBeNull()
        expect(isBannerBlockData({ product: minecraft })).toBe(true)
        expect(isProductSummaryArray([minecraft])).toBe(true)
    })

    it('does not render unavailable products in the marketplace grid', async () => {
        const soldOut = product({
            id: 4,
            slug: 'sold-out-game',
            name: 'Sold Out Game',
            available: false,
            min_price_cents: null,
            compare_at_price_cents: null,
            discount_percent: null,
            available_offer_count: 0,
        })
        mockApi({ catalog: [minecraft, soldOut] })

        renderWithAuth(<MarketplacePage />)

        expect(await screen.findByRole('heading', { name: 'Minecraft' })).toBeVisible()
        expect(screen.queryByRole('heading', { name: 'Sold Out Game' })).not.toBeInTheDocument()
        expect(screen.queryByText('Unavailable')).not.toBeInTheDocument()
    })

    it('searches through the API instead of filtering local data', async () => {
        const fetchMock = mockApi({ catalog: [minecraft], searchResults: [cyberpunk] })

        renderWithAuth(<MarketplacePage />)

        fireEvent.change(screen.getByPlaceholderText(/Search games, gift cards/i), {
            target: { value: 'cyber' },
        })

        expect(await screen.findByRole('heading', { name: 'Cyberpunk 2077' })).toBeVisible()
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/search?q=cyber&category=Games&page=1&per_page=12',
            expect.anything(),
        )
    })

    it('scrolls to the marketplace when a top category is selected', async () => {
        mockApi()

        renderWithAuth(<MarketplacePage />)

        await screen.findByRole('heading', { name: 'Minecraft' })

        const marketplace = document.getElementById('marketplace')
        const scrollIntoView = vi.fn()

        if (marketplace) {
            marketplace.scrollIntoView = scrollIntoView
        }

        const categoryNav = screen.getByRole('navigation', { name: 'Categories' })
        fireEvent.click(within(categoryNav).getByRole('button', { name: 'Gift Cards' }))

        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' })
        expect(screen.getByRole('heading', { name: 'Gift Cards' })).toBeVisible()
    })

    it('uses backend category filtering and pagination instead of slicing a page', async () => {
        const fetchMock = mockApi()

        renderWithAuth(<MarketplacePage />)

        await screen.findByRole('heading', { name: 'Minecraft' })

        const categoryNav = screen.getByRole('navigation', { name: 'Categories' })
        fireEvent.click(within(categoryNav).getByRole('button', { name: 'Hardware' }))

        expect(await screen.findByRole('heading', { name: 'Wireless Controller' })).toBeVisible()
        expect(screen.getByText('1 products')).toBeVisible()
        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/api/products?category=Hardware'),
            expect.anything(),
        )
    })

    it('adds a live product to the real API cart and confirms it on screen', async () => {
        const cart = cartResponse({
            items: [
                {
                    id: 1,
                    product_id: cyberpunk.id,
                    offer_id: 11,
                    quantity: 1,
                    product: cyberpunk,
                    offer: offer({ id: 11, price_cents: 3999 }),
                    line_total_cents: 3999,
                    currency: 'EUR',
                    available: true,
                },
            ],
            total_cents: 3999,
            currency: 'EUR',
        })
        const fetchMock = mockApi({
            detail: productDetail({
                id: cyberpunk.id,
                slug: cyberpunk.slug,
                name: cyberpunk.name,
                offers: [offer({ id: 11, price_cents: 3999, available: true })],
            }),
            cart,
        })

        renderWithAuth(<MarketplacePage />)

        fireEvent.click(await screen.findByRole('button', { name: 'Add Cyberpunk 2077 to cart' }))

        expect(
            await screen.findByText('Cyberpunk 2077 added to cart. Ready for checkout.'),
        ).toBeVisible()
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/cart/items',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ product_id: 2, offer_id: 11 }),
                }),
            ),
        )

        fireEvent.click(screen.getByRole('button', { name: 'View cart' }))

        const drawer = await screen.findByRole('dialog', { name: 'Shopping cart' })
        expect(within(drawer).getByRole('heading', { name: 'Cyberpunk 2077' })).toBeVisible()
        expect(within(drawer).getAllByText('€39.99')).toHaveLength(2)
    })

    it('lets a guest add a product to the cart', async () => {
        const fetchMock = mockApi()

        renderWithAuth(<MarketplacePage />, { user: null })

        fireEvent.click(await screen.findByRole('button', { name: 'Add Minecraft to cart' }))

        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/cart/items',
                expect.objectContaining({ method: 'POST' }),
            ),
        )
        expect(screen.queryByText('Sign in to continue to your cart.')).not.toBeInTheDocument()
        expect(
            await screen.findByText('Minecraft added to cart. Ready for checkout.'),
        ).toBeVisible()
    })

    it('keeps the API cart drawer accessible and restores focus on Escape', async () => {
        mockApi({ cart: cartResponse() })

        renderWithAuth(<MarketplacePage />)

        const cartButton = await screen.findByRole('button', { name: 'Open cart, 1 items' })
        cartButton.focus()
        fireEvent.click(cartButton)

        const drawer = await screen.findByRole('dialog', { name: 'Shopping cart' })
        const closeButton = within(drawer).getByRole('button', { name: 'Close cart drawer' })
        const checkoutLink = await within(drawer).findByRole('link', { name: 'Checkout' })
        expect(document.activeElement).toBe(closeButton)

        checkoutLink.focus()
        fireEvent.keyDown(document, { key: 'Tab' })
        expect(document.activeElement).toBe(closeButton)

        fireEvent.keyDown(document, { key: 'Escape' })
        expect(screen.queryByRole('dialog', { name: 'Shopping cart' })).not.toBeInTheDocument()
        expect(document.activeElement).toBe(cartButton)
    })
})
