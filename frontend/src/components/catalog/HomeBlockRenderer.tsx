import { Link } from 'react-router-dom'
import type { HomeBlock } from '../../types/api'
import { isBannerBlockData, isProductSummaryArray } from '../../types/guards'
import ProductCard from './ProductCard'

export default function HomeBlockRenderer({ block }: { block: HomeBlock }) {
    if (block.type === 'banner') {
        const data = isBannerBlockData(block.data) ? block.data : null

        if (!data?.product?.available) {
            return null
        }

        return (
            <section
                className="home-block home-block--banner"
                aria-labelledby={`block-${block.key}`}
            >
                <div className="banner-copy">
                    <p className="eyebrow">{block.title}</p>
                    <h2 id={`block-${block.key}`}>{data.headline ?? block.title}</h2>
                    <p className="banner-copy__subheadline">{data.subheadline}</p>
                    {data.cta_href && data.cta_label && (
                        <Link className="btn btn--primary" to={data.cta_href}>
                            {data.cta_label}
                        </Link>
                    )}
                </div>

                {data.product && (
                    <div className="banner-product">
                        <ProductCard product={data.product} />
                    </div>
                )}
            </section>
        )
    }

    if (block.type === 'best_sellers' || block.type === 'deals') {
        const products = isProductSummaryArray(block.data)
            ? block.data.filter((product) => product.available)
            : []

        if (products.length === 0) {
            return null
        }

        return (
            <section className="home-block" aria-labelledby={`block-${block.key}`}>
                <header className="section-header">
                    <h2 id={`block-${block.key}`}>{block.title}</h2>
                    <p>{products.length} products to explore</p>
                </header>
                <div className="product-grid">
                    {products.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            </section>
        )
    }

    // WHY: unknown block types are an API-supported state, not an error.
    return null
}
