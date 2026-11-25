import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { bannerBlock, gridBlock, product } from '../../test/fixtures'
import HomeBlockRenderer from './HomeBlockRenderer'

function renderBlock(block: Parameters<typeof HomeBlockRenderer>[0]['block']) {
    return render(
        <MemoryRouter>
            <HomeBlockRenderer block={block} />
        </MemoryRouter>,
    )
}

describe('HomeBlockRenderer', () => {
    it('renders only content supplied by the API for banner blocks', () => {
        renderBlock(bannerBlock())

        expect(screen.getByText('Featured this week')).toBeInTheDocument()
        expect(screen.getByText('A great price')).toBeInTheDocument()
        expect(screen.getByRole('link', { name: 'Buy now' })).toHaveAttribute(
            'href',
            '/products/minecraft',
        )
        expect(screen.getByText('Minecraft')).toBeInTheDocument()
    })

    it('renders available products for product grid blocks', () => {
        renderBlock(gridBlock())

        expect(screen.getByRole('heading', { name: 'Best sellers' })).toBeInTheDocument()
        expect(screen.getByText('Minecraft')).toBeInTheDocument()
        expect(screen.getByText('€19.99')).toBeInTheDocument()
    })

    it('hides a banner whose featured product is unavailable', () => {
        const { container } = renderBlock(
            bannerBlock({
                data: {
                    headline: 'Sold out feature',
                    subheadline: 'No longer available',
                    cta_label: 'View deal',
                    cta_href: '/products/sold-out-game',
                    product: product({ name: 'Sold Out Game', available: false }),
                },
            }),
        )

        expect(container).toBeEmptyDOMElement()
    })

    it('hides sold-out products from product grid blocks', () => {
        const { container } = renderBlock(
            gridBlock({
                data: [product({ name: 'Sold Out Game', available: false })],
            }),
        )

        expect(container).toBeEmptyDOMElement()
        expect(screen.queryByText('Sold Out Game')).not.toBeInTheDocument()
    })

    it('ignores unknown block types and malformed data', () => {
        const { container } = renderBlock(
            gridBlock({ type: 'newsletter', data: [{ hardcoded: true }] }),
        )

        expect(container).toBeEmptyDOMElement()
    })
})
