import { Gamepad2, Monitor, Tv } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { ProductSummary } from '../../types/api'
import { coverColors } from '../../utils/coverColors'
import { formatPrice } from '../../utils/format'
import CoverArt from '../ui/CoverArt'

interface MarketplaceProductCardProps {
    product: ProductSummary
    busy?: boolean
    onAdd: (product: ProductSummary) => void
}

function PlatformIcon({ platform }: { platform: string }) {
    const normalized = platform.toLowerCase()
    const Icon = normalized.includes('playstation')
        ? Tv
        : normalized.includes('xbox') || normalized.includes('epic')
          ? Gamepad2
          : Monitor

    return (
        <span className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-indigo-deep px-2 py-1 text-[10px] font-bold text-white shadow-lg">
            <Icon className="h-3.5 w-3.5" />
            {platform}
        </span>
    )
}

/** Purple marketplace card for API-backed products. Prices come from the backend. */
export default function MarketplaceProductCard({
    product,
    busy = false,
    onAdd,
}: MarketplaceProductCardProps) {
    return (
        <article className="group relative rounded-2xl border border-white/10 bg-royal p-3 pb-14 transition duration-200 hover:-translate-y-1 hover:border-sun/40 hover:bg-indigo-deep">
            <Link to={`/products/${product.slug}`} className="block">
                <div className="relative">
                    <CoverArt
                        title={product.name}
                        colors={coverColors(product.slug)}
                        className="aspect-[3/4] w-full rounded-xl"
                    />
                    <div className="absolute -bottom-3 left-3 z-20">
                        <PlatformIcon platform={product.platform} />
                    </div>
                </div>

                <div className="mt-6">
                    <p className="truncate text-[11px] font-semibold tracking-wide text-lavender uppercase">
                        {product.genre ?? 'Digital key'}
                    </p>
                    <h3 className="mt-1 line-clamp-2 min-h-10 text-sm leading-tight font-bold text-white">
                        {product.name}
                    </h3>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            className={`inline-flex items-center gap-1.5 text-[11px] font-black tracking-wide uppercase ${
                                product.available ? 'text-trust' : 'text-promo'
                            }`}
                        >
                            <span
                                className={`h-1.5 w-1.5 rounded-full ${
                                    product.available ? 'bg-trust' : 'bg-promo'
                                }`}
                            />
                            {product.available
                                ? `${product.available_offer_count} offers`
                                : 'Unavailable'}
                        </span>
                        {(product.discount_percent ?? 0) > 0 && (
                            <span className="rounded-full bg-promo px-2 py-0.5 text-[10px] font-black text-white">
                                -{product.discount_percent}%
                            </span>
                        )}
                    </div>

                    <div className="mt-4 border-t border-white/10 pt-3">
                        <p className="text-[10px] font-semibold tracking-wider text-lavender uppercase">
                            From
                        </p>
                        <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span className="text-xl font-black text-white">
                                {formatPrice(product.min_price_cents, product.currency)}
                            </span>
                            {product.compare_at_price_cents !== null && (
                                <s className="text-xs text-lavender/70">
                                    {formatPrice(product.compare_at_price_cents, product.currency)}
                                </s>
                            )}
                        </div>
                    </div>
                </div>
            </Link>

            <button
                type="button"
                onClick={() => onAdd(product)}
                disabled={!product.available || busy}
                aria-label={`Add ${product.name} to cart`}
                className="absolute inset-x-3 bottom-3 rounded-xl bg-sun py-2.5 text-xs font-black text-midnight transition hover:bg-white focus-visible:opacity-100 disabled:cursor-not-allowed disabled:opacity-40 lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100"
            >
                {busy ? 'Adding…' : 'Add to cart'}
            </button>
        </article>
    )
}
