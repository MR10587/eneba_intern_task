import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { bannerBlock, product } from '../../test/fixtures'
import HeroGrid from './HeroGrid'

describe('HeroGrid', () => {
    it('hides a featured game that is unavailable', () => {
        const { container } = render(
            <MemoryRouter>
                <HeroGrid
                    blocks={[
                        bannerBlock({
                            data: {
                                headline: 'Featured game',
                                subheadline: 'Ready to play',
                                cta_label: 'View deal',
                                cta_href: '/products/sold-out-game',
                                product: product({
                                    name: 'Sold Out Game',
                                    slug: 'sold-out-game',
                                    available: false,
                                }),
                            },
                        }),
                    ]}
                    onSelectCategory={vi.fn()}
                />
            </MemoryRouter>,
        )

        expect(container).toBeEmptyDOMElement()
        expect(screen.queryByText('Featured game')).not.toBeInTheDocument()
    })

    it('shows a featured game that is available', () => {
        render(
            <MemoryRouter>
                <HeroGrid blocks={[bannerBlock()]} onSelectCategory={vi.fn()} />
            </MemoryRouter>,
        )

        expect(screen.getByText('Featured this week')).toBeVisible()
        expect(screen.getByRole('link', { name: 'Buy now' })).toBeVisible()
    })
})
