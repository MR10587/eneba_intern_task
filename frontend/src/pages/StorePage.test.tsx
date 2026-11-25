import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { jsonResponse, product } from '../test/fixtures'
import StorePage from './StorePage'

function LocationProbe() {
    const location = useLocation()

    return <div data-testid="location">{location.search}</div>
}

function stubApi(): void {
    const fetchMock = vi.fn(async (input: string) => {
        const url = String(input)

        if (url.startsWith('/api/products/filters')) {
            return jsonResponse({
                data: {
                    categories: ['Games', 'Gift Cards', 'Top-Ups', 'Hardware', 'Best Deals'],
                    platforms: ['Steam', 'Epic Games'],
                    regions: ['Europe', 'Global'],
                    price_range: { min_cents: 499, max_cents: 5999 },
                    currency: 'EUR',
                },
            })
        }

        return jsonResponse({
            data: [product()],
            meta: {
                pagination: {
                    current_page: 1,
                    per_page: 12,
                    total: 1,
                    last_page: 1,
                    from: 1,
                    to: 1,
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
    })

    vi.stubGlobal('fetch', fetchMock)
}

describe('StorePage', () => {
    it('loads backend filters and products', async () => {
        stubApi()

        render(
            <MemoryRouter initialEntries={['/products']}>
                <LocationProbe />
                <StorePage />
            </MemoryRouter>,
        )

        expect(await screen.findByText('Minecraft')).toBeInTheDocument()
        expect(screen.getByRole('option', { name: 'Steam' })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: 'Europe' })).toBeInTheDocument()
        expect(screen.getByText('1 games')).toBeInTheDocument()
    })

    it('writes filter changes into the URL so results stay shareable', async () => {
        stubApi()

        render(
            <MemoryRouter initialEntries={['/products?platform=Steam']}>
                <LocationProbe />
                <StorePage />
            </MemoryRouter>,
        )

        expect(await screen.findByText('Minecraft')).toBeInTheDocument()
        expect(screen.getByLabelText('Platform')).toHaveValue('Steam')

        fireEvent.change(screen.getByLabelText('Region'), { target: { value: 'Europe' } })

        await waitFor(() => {
            expect(screen.getByTestId('location')).toHaveTextContent('region=Europe')
        })
        expect(screen.getByTestId('location')).toHaveTextContent('platform=Steam')
    })
})
