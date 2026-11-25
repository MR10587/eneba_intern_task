import { SearchX } from 'lucide-react'
import type { ProductSummary } from '../../types/api'
import MarketplaceProductCard from './MarketplaceProductCard'

interface ProductGridProps {
    products: ProductSummary[]
    busySlug?: string | null
    onAdd: (product: ProductSummary) => void
}

export default function ProductGrid({ products, busySlug, onAdd }: ProductGridProps) {
    if (products.length === 0) {
        return (
            <div className="grid min-h-72 place-items-center rounded-2xl border border-dashed border-white/15 bg-royal/50 p-8 text-center">
                <div>
                    <SearchX className="mx-auto h-10 w-10 text-lavender" />
                    <h3 className="mt-3 text-lg font-bold text-white">No products found</h3>
                    <p className="mt-1 text-sm text-lavender">
                        Try a different search, category, or filter.
                    </p>
                </div>
            </div>
        )
    }

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {products.map((product) => (
                <MarketplaceProductCard
                    key={product.id}
                    product={product}
                    busy={busySlug === product.slug}
                    onAdd={onAdd}
                />
            ))}
        </div>
    )
}
