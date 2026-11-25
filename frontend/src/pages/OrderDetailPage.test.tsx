import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse, orderResponse } from '../test/fixtures'
import OrderDetailPage from './OrderDetailPage'

describe('OrderDetailPage', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('shows the confirmation banner and delivered key after checkout', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(orderResponse()))
        vi.stubGlobal('fetch', fetchMock)

        render(
            <MemoryRouter initialEntries={[{ pathname: '/orders/7', state: { justPlaced: true } }]}>
                <Routes>
                    <Route path="/orders/:id" element={<OrderDetailPage />} />
                </Routes>
            </MemoryRouter>,
        )

        expect(await screen.findByText('Payment succeeded')).toBeInTheDocument()
        expect(screen.getByText('KEY-ABCD-EFGH-IJKL')).toBeInTheDocument()
        expect(fetchMock).toHaveBeenCalledWith('/api/orders/7', expect.anything())
    })
})
