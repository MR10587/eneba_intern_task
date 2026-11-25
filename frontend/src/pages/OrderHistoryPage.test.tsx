import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { authUser, jsonResponse, orderResponse } from '../test/fixtures'
import { renderWithAuth } from '../test/renderAuth'
import OrderHistoryPage from './OrderHistoryPage'

describe('OrderHistoryPage', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('lists the signed-in user orders from the API with detail links', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [orderResponse().data] }))
        vi.stubGlobal('fetch', fetchMock)

        renderWithAuth(<OrderHistoryPage />, { user: authUser() })

        expect(await screen.findByRole('heading', { name: 'Order history' })).toBeInTheDocument()
        expect(screen.getByText('Order #7')).toBeInTheDocument()
        expect(screen.getByRole('link', { name: 'View keys and details' })).toHaveAttribute(
            'href',
            '/orders/7',
        )
        expect(fetchMock).toHaveBeenCalledWith('/api/orders', expect.anything())
    })
})
