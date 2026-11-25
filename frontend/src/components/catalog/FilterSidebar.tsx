import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import type { ReactNode } from 'react'
import type { CatalogFilters } from '../../types/api'
import { formatPrice } from '../../utils/format'

interface FilterSidebarProps {
    filters: CatalogFilters | null
    selectedPlatform: string
    selectedRegion: string
    maxPriceCents: number | null
    onPlatformChange: (platform: string) => void
    onRegionChange: (region: string) => void
    onMaxPriceChange: (value: number) => void
    onReset: () => void
}

type SectionKey = 'platform' | 'region' | 'price'

export default function FilterSidebar({
    filters,
    selectedPlatform,
    selectedRegion,
    maxPriceCents,
    onPlatformChange,
    onRegionChange,
    onMaxPriceChange,
    onReset,
}: FilterSidebarProps) {
    const [open, setOpen] = useState<Record<SectionKey, boolean>>({
        platform: true,
        region: true,
        price: true,
    })

    const lowerBound = filters?.price_range.min_cents ?? 0
    const upperBound = filters?.price_range.max_cents ?? 0
    const priceValue = maxPriceCents ?? upperBound

    function toggleSection(key: SectionKey): void {
        setOpen((current) => ({ ...current, [key]: !current[key] }))
    }

    return (
        <aside
            className="rounded-2xl border border-white/10 bg-royal p-5"
            aria-label="Product filters"
        >
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-black tracking-widest text-white uppercase">Filters</h2>
                <button
                    type="button"
                    onClick={onReset}
                    className="text-xs font-semibold text-lavender underline-offset-2 hover:text-sun hover:underline"
                >
                    Reset
                </button>
            </div>

            <Section
                title="Platform"
                expanded={open.platform}
                onToggle={() => toggleSection('platform')}
            >
                <div className="space-y-3" role="radiogroup" aria-label="Platform">
                    <label className="flex cursor-pointer items-center gap-3 text-sm text-lavender hover:text-white">
                        <input
                            type="radio"
                            name="marketplace-platform"
                            checked={selectedPlatform === ''}
                            onChange={() => onPlatformChange('')}
                            className="h-4 w-4 accent-[#FAD318]"
                        />
                        All platforms
                    </label>
                    {filters?.platforms.map((platform) => (
                        <label
                            key={platform}
                            className="flex cursor-pointer items-center gap-3 text-sm text-lavender hover:text-white"
                        >
                            <input
                                type="radio"
                                name="marketplace-platform"
                                checked={selectedPlatform === platform}
                                onChange={() => onPlatformChange(platform)}
                                className="h-4 w-4 accent-[#FAD318]"
                            />
                            {platform}
                        </label>
                    ))}
                </div>
            </Section>

            <Section title="Region" expanded={open.region} onToggle={() => toggleSection('region')}>
                <div className="space-y-3" role="radiogroup" aria-label="Region">
                    <label className="flex cursor-pointer items-center gap-3 text-sm text-lavender hover:text-white">
                        <input
                            type="radio"
                            name="marketplace-region"
                            checked={selectedRegion === ''}
                            onChange={() => onRegionChange('')}
                            className="h-4 w-4 accent-[#FAD318]"
                        />
                        All regions
                    </label>
                    {filters?.regions.map((region) => (
                        <label
                            key={region}
                            className="flex cursor-pointer items-center gap-3 text-sm text-lavender hover:text-white"
                        >
                            <input
                                type="radio"
                                name="marketplace-region"
                                checked={selectedRegion === region}
                                onChange={() => onRegionChange(region)}
                                className="h-4 w-4 accent-[#FAD318]"
                            />
                            {region}
                        </label>
                    ))}
                </div>
            </Section>

            <Section
                title="Price range"
                expanded={open.price}
                onToggle={() => toggleSection('price')}
            >
                <div className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-lavender">Up to</span>
                        <span className="font-bold text-sun">
                            {formatPrice(priceValue, filters?.currency)}
                        </span>
                    </div>
                    <input
                        type="range"
                        min={lowerBound}
                        max={upperBound}
                        step={100}
                        value={priceValue}
                        onChange={(event) => onMaxPriceChange(Number(event.target.value))}
                        aria-label="Maximum price"
                        className="w-full accent-[#FAD318]"
                    />
                    <p className="text-xs text-lavender/70">Slide to filter by maximum price.</p>
                </div>
            </Section>
        </aside>
    )
}

interface SectionProps {
    title: string
    expanded: boolean
    onToggle: () => void
    children: ReactNode
}

function Section({ title, expanded, onToggle, children }: SectionProps) {
    return (
        <div className="border-t border-white/10 py-4 first:border-t-0 first:pt-0">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={expanded}
                className="flex w-full items-center justify-between text-left text-sm font-bold text-white"
            >
                {title}
                <ChevronDown
                    className={`h-4 w-4 text-lavender transition-transform ${
                        expanded ? 'rotate-180' : ''
                    }`}
                />
            </button>
            {expanded && <div className="mt-4">{children}</div>}
        </div>
    )
}
