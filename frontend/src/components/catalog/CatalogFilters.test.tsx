import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import type { CatalogFilters as CatalogFiltersData } from '../../types/api'
import CatalogFilters from './CatalogFilters'

const filters: CatalogFiltersData = {
    categories: ['Games', 'Gift Cards', 'Top-Ups', 'Hardware', 'Best Deals'],
    platforms: ['Steam'],
    regions: ['Europe'],
    price_range: { min_cents: 0, max_cents: 5000 },
    currency: 'EUR',
}

describe('CatalogFilters', () => {
    it('keeps min_price <= max_price when either range input crosses the other', () => {
        const onChange = vi.fn()

        render(
            <CatalogFilters
                filters={filters}
                value={{ min_price: 4000, max_price: 2000 }}
                onChange={onChange}
            />,
        )

        const [minInput, maxInput] = screen.getAllByRole('slider')

        fireEvent.change(minInput, { target: { value: '3000' } })
        expect(onChange).toHaveBeenLastCalledWith({ min_price: 2000, page: 1 })

        fireEvent.change(maxInput, { target: { value: '1000' } })
        expect(onChange).toHaveBeenLastCalledWith({ max_price: 4000, page: 1 })
    })
})
