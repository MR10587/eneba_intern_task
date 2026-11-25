import { ArrowUpRight, Sparkles, TrendingUp } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { HomeBlock } from '../../types/api'
import type { Category } from '../../types'
import { isBannerBlockData, isProductSummaryArray } from '../../types/guards'
import { coverColors } from '../../utils/coverColors'
import CoverArt from '../ui/CoverArt'

interface HeroGridProps {
    blocks: HomeBlock[]
    onSelectCategory: (category: Category) => void
}

export default function HeroGrid({ blocks, onSelectCategory }: HeroGridProps) {
    const bannerCandidate = blocks.find(
        (block) => block.type === 'banner' && isBannerBlockData(block.data),
    )
    // A banner without a purchasable featured product is not a recommendation.
    const banner =
        bannerCandidate &&
        isBannerBlockData(bannerCandidate.data) &&
        bannerCandidate.data.product?.available
            ? bannerCandidate
            : null
    const collections = blocks
        .filter(
            (block) =>
                (block.type === 'best_sellers' || block.type === 'deals') &&
                isProductSummaryArray(block.data) &&
                block.data.some((product) => product.available),
        )
        .slice(0, 2)

    if (!banner && collections.length === 0) {
        return null
    }

    return (
        <section className="grid grid-cols-1 gap-3 lg:grid-cols-3" aria-label="Store highlights">
            {banner && isBannerBlockData(banner.data) && (
                <article className="group relative min-h-64 overflow-hidden rounded-2xl border border-white/10 bg-linear-to-br from-[#8A2BE2] via-[#5B18A8] to-[#1F0A4D] p-6 lg:col-span-2 lg:min-h-[360px] lg:p-10">
                    <div className="absolute -top-16 -right-12 h-52 w-52 rounded-full bg-white/10 blur-3xl transition-transform duration-500 group-hover:scale-125" />
                    <div className="relative z-10 flex h-full flex-col items-start">
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold tracking-wider text-white uppercase backdrop-blur-sm">
                            <Sparkles className="h-3.5 w-3.5" />
                            {banner.title}
                        </span>
                        <h2 className="mt-4 max-w-lg text-3xl leading-tight font-black text-white lg:text-5xl">
                            {banner.data.headline ?? banner.title}
                        </h2>
                        {banner.data.subheadline && (
                            <p className="mt-2 max-w-md text-sm text-white/80">
                                {banner.data.subheadline}
                            </p>
                        )}
                        {banner.data.cta_href && banner.data.cta_label && (
                            <Link
                                to={banner.data.cta_href}
                                className="mt-auto inline-flex items-center gap-2 rounded-xl bg-sun px-4 py-2.5 text-sm font-black text-midnight transition-transform hover:translate-x-1"
                            >
                                {banner.data.cta_label}
                                <ArrowUpRight className="h-4 w-4" />
                            </Link>
                        )}
                    </div>

                    {banner.data.product?.available && (
                        <div className="absolute right-6 bottom-6 hidden w-32 rotate-3 overflow-hidden rounded-xl shadow-2xl lg:block">
                            <CoverArt
                                title={banner.data.product.name}
                                colors={coverColors(banner.data.product.slug)}
                                className="aspect-[3/4] w-full"
                            />
                        </div>
                    )}
                </article>
            )}

            {collections.map((block) => {
                const products = isProductSummaryArray(block.data)
                    ? block.data.filter((product) => product.available)
                    : []
                const featured = products[0]
                const category: Category = block.type === 'deals' ? 'Best Deals' : 'Games'

                return (
                    <article
                        key={block.key}
                        className="group relative flex min-h-52 flex-col overflow-hidden rounded-2xl border border-white/10 bg-linear-to-br from-royal to-indigo-deep p-6"
                    >
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold tracking-wider text-sun uppercase">
                            <TrendingUp className="h-3.5 w-3.5" />
                            Featured
                        </span>
                        <h2 className="mt-4 text-2xl font-black text-white">{block.title}</h2>
                        <p className="mt-1 text-sm text-lavender">
                            {products.length} products to explore
                        </p>
                        {featured && (
                            <div className="absolute right-4 bottom-4 w-20 rotate-6 overflow-hidden rounded-lg opacity-80 transition-transform group-hover:rotate-3">
                                <CoverArt
                                    title={featured.name}
                                    colors={coverColors(featured.slug)}
                                    className="aspect-[3/4] w-full"
                                />
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={() => onSelectCategory(category)}
                            className="mt-auto inline-flex w-fit items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-black text-white transition hover:bg-sun hover:text-midnight"
                        >
                            Explore {block.title.toLowerCase()}
                            <ArrowUpRight className="h-4 w-4" />
                        </button>
                    </article>
                )
            })}
        </section>
    )
}
