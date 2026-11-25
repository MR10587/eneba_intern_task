import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { jsonResponse, product } from '../test/fixtures'
import SearchPage from './SearchPage'

function stubSearch(results: ReturnType<typeof product>[], query: string): void {
    const fetchMock = vi.fn(async () =>
        jsonResponse({
            data: results,
            meta: {
                query,
                result_count: results.length,
                applied_filters: {
                    category: null,
                    platform: null,
                    region: null,
                    min_price_cents: null,
                    max_price_cents: null,
                    sort: 'relevance',
                    page: 1,
                    per_page: 12,
                },
                pagination: {
                    current_page: 1,
                    per_page: 12,
                    total: results.length,
                    last_page: 1,
                    from: results.length ? 1 : null,
                    to: results.length || null,
                },
            },
        }),
    )

    vi.stubGlobal('fetch', fetchMock)
}

describe('SearchPage', () => {
    it('shows the backend result count and products', async () => {
        stubSearch([product({ name: 'Minecraft', slug: 'minecraft' })], 'mincraft')

        render(
            <MemoryRouter initialEntries={['/search?q=mincraft']}>
                <SearchPage />
            </MemoryRouter>,
        )

        expect(await screen.findByText('Minecraft')).toBeInTheDocument()
        expect(screen.getByText('1 results')).toBeInTheDocument()
        expect(screen.getByRole('heading', { name: 'Results for “mincraft”' })).toBeInTheDocument()
    })

    it('shows a backend-driven empty state', async () => {
        stubSearch([], 'zzzz')

        render(
            <MemoryRouter initialEntries={['/search?q=zzzz']}>
                <SearchPage />
            </MemoryRouter>,
        )

        expect(await screen.findByText('No matches')).toBeInTheDocument()
        expect(screen.getByText('0 results')).toBeInTheDocument()
    })

    it('asks for a term when the query is too short', () => {
        stubSearch([], '')

        render(
            <MemoryRouter initialEntries={['/search']}>
                <SearchPage />
            </MemoryRouter>,
        )

        expect(screen.getByText('Enter a search term')).toBeInTheDocument()
    })
})
