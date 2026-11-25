import { fireEvent, screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { authUser, cartResponse, jsonResponse, orderResponse } from '../test/fixtures'
import { renderWithAuth } from '../test/renderAuth'
import CheckoutPage from './CheckoutPage'

describe('CheckoutPage', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('surfaces a simulated payment failure from the API', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(cartResponse()))
            .mockResolvedValueOnce(
                jsonResponse({ message: 'Card was declined.', code: 'PAYMENT_FAILED' }, 402),
            )
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(<CheckoutPage />, { user: authUser() })

        expect(await screen.findByText('Review your items')).toBeInTheDocument()

        fireEvent.change(screen.getByLabelText('Card number'), {
            target: { value: '4242 4242 4242 0002' },
        })
        fireEvent.click(screen.getByRole('button', { name: /Pay €19.99/ }))

        expect(await screen.findByRole('alert')).toHaveTextContent('Card was declined.')
    })

    it('redirects a cart with unavailable items back to the cart', async () => {
        const staleCart = cartResponse()
        staleCart.data.items[0].available = false
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(staleCart)))

        renderWithAuth(
            <Routes>
                <Route path="/checkout" element={<CheckoutPage />} />
                <Route path="/cart" element={<p>Cart screen</p>} />
            </Routes>,
            { route: '/checkout', user: authUser() },
        )

        expect(await screen.findByText('Cart screen')).toBeVisible()
        expect(screen.queryByText('Review your items')).not.toBeInTheDocument()
    })

    it('redirects to the delivered order after a successful payment', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(cartResponse()))
            .mockResolvedValueOnce(
                jsonResponse({ data: orderResponse().data, meta: { keys_delivered: 1 } }, 201),
            )
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(
            <Routes>
                <Route path="/checkout" element={<CheckoutPage />} />
                <Route path="/orders/:id" element={<p>Order confirmation page</p>} />
            </Routes>,
            { route: '/checkout', user: authUser() },
        )

        fireEvent.change(await screen.findByLabelText('Card number'), {
            target: { value: '4242 4242 4242 4242' },
        })
        fireEvent.click(screen.getByRole('button', { name: /Pay €19.99/ }))

        expect(await screen.findByText('Order confirmation page')).toBeInTheDocument()
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/checkout',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ card_number: '4242 4242 4242 4242' }),
            }),
        )
    })
})
