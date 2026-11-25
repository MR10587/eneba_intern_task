import { render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { bannerBlock, cartResponse, jsonResponse, product } from './test/fixtures'
import App from './App'

describe('App routing', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('renders the live marketplace at the root route', async () => {
        window.localStorage.clear()
        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: string | URL | Request) => {
                const url = typeof input === 'string' ? input : input.toString()

                if (url.startsWith('/api/home')) {
                    return jsonResponse({
                        data: [
                            bannerBlock({
                                data: {
                                    headline: 'API featured headline',
                                    subheadline: 'Backend copy',
                                    cta_label: 'View deal',
                                    cta_href: '/products/minecraft',
                                    product: product(),
                                },
                            }),
                        ],
                        meta: { block_count: 1 },
                    })
                }

                if (url.startsWith('/api/products/filters')) {
                    return jsonResponse({
                        data: {
                            categories: [
                                'Games',
                                'Gift Cards',
                                'Top-Ups',
                                'Hardware',
                                'Best Deals',
                            ],
                            platforms: ['Steam'],
                            regions: ['Europe'],
                            price_range: { min_cents: 0, max_cents: 5000 },
                            currency: 'EUR',
                        },
                    })
                }

                return jsonResponse({
                    data: [],
                    meta: {
                        pagination: {
                            current_page: 1,
                            per_page: 12,
                            total: 0,
                            last_page: 1,
                            from: null,
                            to: null,
                        },
                        applied_filters: {
                            category: null,
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
            }),
        )

        render(<App />)

        expect(screen.getByLabelText('Eneba home')).toBeInTheDocument()
        expect(await screen.findByRole('heading', { name: 'API featured headline' })).toBeVisible()
        expect(screen.getByText('Live marketplace')).toBeVisible()
    })

    it('lets a guest open the cart route without signing in', async () => {
        window.localStorage.clear()
        window.history.pushState({}, '', '/cart')

        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: string | URL | Request) => {
                const url = typeof input === 'string' ? input : input.toString()

                if (url.startsWith('/api/cart')) {
                    return jsonResponse(cartResponse())
                }

                return jsonResponse({ data: {}, meta: {} })
            }),
        )

        render(<App />)

        expect(await screen.findByRole('heading', { name: '1 items' })).toBeVisible()
        expect(screen.getByText('Order total')).toBeVisible()
        expect(screen.getByText('Sign in at checkout to place your order.')).toBeVisible()

        window.history.pushState({}, '', '/')
    })
})
