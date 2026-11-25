import { Link } from 'react-router-dom'
import type { ProductSummary } from '../../types/api'
import Badge from '../ui/Badge'
import Price from '../ui/Price'

/** Card for API-backed products. Prices and availability always come from the backend. */
export default function ProductCard({ product }: { product: ProductSummary }) {
    return (
        <article className="product-card">
            <Link className="product-card__link" to={`/products/${product.slug}`}>
                <div
                    className="product-card__cover"
                    role="img"
                    aria-label={`${product.name} cover placeholder`}
                >
                    <span aria-hidden="true">K</span>
                </div>

                <div className="product-card__body">
                    <p className="product-card__meta-line">
                        {product.platform}
                        {product.genre ? ` · ${product.genre}` : ''}
                    </p>
                    <h3 className="product-card__title">{product.name}</h3>

                    <div className="product-card__badges">
                        <Badge tone={product.available ? 'success' : 'danger'}>
                            {product.available ? 'Available' : 'Unavailable'}
                        </Badge>
                        {product.discount_percent !== null && product.discount_percent > 0 && (
                            <Badge tone="accent">-{product.discount_percent}%</Badge>
                        )}
                    </div>

                    <Price
                        cents={product.min_price_cents}
                        currency={product.currency}
                        compareAt={product.compare_at_price_cents}
                    />
                </div>
            </Link>
        </article>
    )
}
