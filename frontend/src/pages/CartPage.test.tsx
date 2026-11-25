import { fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { authUser, cartResponse, jsonResponse, product } from '../test/fixtures'
import { renderWithAuth } from '../test/renderAuth'
import CartPage from './CartPage'

describe('CartPage', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('shows the order total and removes a line from the cart', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(cartResponse()))
            .mockResolvedValueOnce(jsonResponse(cartResponse()))
            .mockResolvedValueOnce(
                jsonResponse(
                    cartResponse(
                        { items: [], total_cents: 0 },
                        { item_count: 0, available_item_count: 0 },
                    ),
                ),
            )
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(<CartPage />, { user: authUser() })

        expect(await screen.findByText('Order total')).toBeInTheDocument()
        expect(screen.getAllByText('€19.99')).toHaveLength(2)

        fireEvent.click(screen.getByRole('button', { name: 'Remove' }))

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                '/api/cart/items/10',
                expect.objectContaining({ method: 'DELETE' }),
            )
        })
        expect(await screen.findByText('Your cart is empty')).toBeInTheDocument()
    })

    it('blocks checkout when an API cart item is unavailable', async () => {
        const staleCart = cartResponse()
        staleCart.data.items[0].available = false
        staleCart.data.items[0].product = product({ name: 'Unavailable Game' })
        staleCart.meta.available_item_count = 0
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(staleCart)))

        renderWithAuth(<CartPage />, { user: authUser() })

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Unavailable: Unavailable Game. Remove these items before checkout.',
        )
        expect(screen.getByText('Unavailable')).toBeVisible()
        expect(screen.getByRole('button', { name: 'Continue to checkout' })).toBeDisabled()
        expect(screen.queryByRole('link', { name: 'Continue to checkout' })).not.toBeInTheDocument()
    })

    it('shows a guest cart with a sign-in reminder at checkout', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(cartResponse())))

        renderWithAuth(<CartPage />, { user: null })

        expect(await screen.findByText('Order total')).toBeInTheDocument()
        expect(screen.getByText('Sign in at checkout to place your order.')).toBeVisible()
        expect(screen.getByRole('link', { name: 'Continue to checkout' })).toBeVisible()
    })
})
