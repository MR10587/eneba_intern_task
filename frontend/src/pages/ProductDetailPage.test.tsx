import { fireEvent, screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { authUser, cartResponse, jsonResponse, productDetail } from '../test/fixtures'
import { renderWithAuth } from '../test/renderAuth'
import ProductDetailPage from './ProductDetailPage'

describe('ProductDetailPage', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('renders backend offers and adds a selected offer to the API cart', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({ data: productDetail(), meta: { available_offer_count: 1 } }),
            )
            .mockResolvedValueOnce(jsonResponse(cartResponse(), 201))
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(
            <Routes>
                <Route path="/products/minecraft" element={<ProductDetailPage />} />
                <Route path="/cart" element={<p>Cart page</p>} />
            </Routes>,
            { route: '/products/minecraft', user: authUser() },
        )

        expect(await screen.findByRole('heading', { name: 'Seller offers' })).toBeInTheDocument()
        expect(screen.getByText('KeyKingdom')).toBeInTheDocument()
        expect(screen.getAllByText('€19.99')).toHaveLength(2)

        fireEvent.click(screen.getByRole('button', { name: 'Add to cart' }))

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/cart/items',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ product_id: 1, offer_id: 10 }),
                }),
            )
        })
        expect(await screen.findByText('Cart page')).toBeInTheDocument()
        expect(screen.getByText('Minecraft added to cart. Ready for checkout.')).toBeInTheDocument()
    })

    it('lets a guest add an offer without signing in', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({ data: productDetail(), meta: { available_offer_count: 1 } }),
            )
            .mockResolvedValueOnce(jsonResponse(cartResponse(), 201))
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(
            <Routes>
                <Route path="/products/minecraft" element={<ProductDetailPage />} />
                <Route path="/cart" element={<p>Cart page</p>} />
            </Routes>,
            { route: '/products/minecraft', user: null },
        )

        expect(await screen.findByRole('button', { name: 'Add to cart' })).toBeEnabled()
        expect(screen.queryByText(/Sign in to add/i)).not.toBeInTheDocument()

        fireEvent.click(screen.getByRole('button', { name: 'Add to cart' }))

        await waitFor(() => {
            const request = fetchMock.mock.calls.find(([url]) => url === '/api/cart/items')

            if (!request) {
                throw new Error('Cart request was not made')
            }

            expect(request[1]).toMatchObject({ method: 'POST' })
            expect(request[1].headers).toBeInstanceOf(Headers)
            expect((request[1].headers as Headers).get('X-Cart-Token')).toBeTruthy()
        })
        expect(await screen.findByText('Cart page')).toBeInTheDocument()
    })
})
