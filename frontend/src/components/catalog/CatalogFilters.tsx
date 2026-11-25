import type { ProductQuery } from '../../api/endpoints'
import type { CatalogFilters as CatalogFiltersData, SortOption } from '../../types/api'
import { formatPrice } from '../../utils/format'
import Button from '../ui/Button'

const sortOptions: Array<{ value: SortOption; label: string }> = [
    { value: 'popularity', label: 'Popularity' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'relevance', label: 'Relevance' },
]

interface CatalogFiltersProps {
    filters: CatalogFiltersData | null
    value: ProductQuery
    onChange: (patch: ProductQuery) => void
}

export default function CatalogFilters({ filters, value, onChange }: CatalogFiltersProps) {
    const bounds = filters?.price_range
    const hasPriceBounds = bounds?.min_cents !== null && bounds?.min_cents !== undefined
    const lowerBound = bounds?.min_cents ?? 0
    const upperBound = bounds?.max_cents ?? 0
    const minCents = value.min_price ?? lowerBound
    const maxCents = value.max_price ?? upperBound

    return (
        <form
            className="catalog-filters"
            aria-label="Catalog filters"
            onSubmit={(e) => e.preventDefault()}
        >
            <label>
                <span>Platform</span>
                <select
                    value={value.platform ?? ''}
                    disabled={!filters}
                    onChange={(event) =>
                        onChange({ platform: event.target.value || null, page: 1 })
                    }
                >
                    <option value="">All platforms</option>
                    {filters?.platforms.map((platform) => (
                        <option key={platform} value={platform}>
                            {platform}
                        </option>
                    ))}
                </select>
            </label>

            <label>
                <span>Region</span>
                <select
                    value={value.region ?? ''}
                    disabled={!filters}
                    onChange={(event) => onChange({ region: event.target.value || null, page: 1 })}
                >
                    <option value="">All regions</option>
                    {filters?.regions.map((region) => (
                        <option key={region} value={region}>
                            {region}
                        </option>
                    ))}
                </select>
            </label>

            <label>
                <span>Sort</span>
                <select
                    value={value.sort ?? 'popularity'}
                    onChange={(event) =>
                        onChange({ sort: event.target.value as SortOption, page: 1 })
                    }
                >
                    {sortOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </label>

            {hasPriceBounds && bounds && (
                <>
                    <label>
                        <span>
                            Min price <strong>{formatPrice(minCents, filters?.currency)}</strong>
                        </span>
                        <input
                            type="range"
                            min={lowerBound}
                            max={upperBound}
                            step={100}
                            value={minCents}
                            onChange={(event) =>
                                onChange({
                                    min_price: Math.min(Number(event.target.value), maxCents),
                                    page: 1,
                                })
                            }
                        />
                    </label>

                    <label>
                        <span>
                            Max price <strong>{formatPrice(maxCents, filters?.currency)}</strong>
                        </span>
                        <input
                            type="range"
                            min={lowerBound}
                            max={upperBound}
                            step={100}
                            value={maxCents}
                            onChange={(event) =>
                                onChange({
                                    max_price: Math.max(Number(event.target.value), minCents),
                                    page: 1,
                                })
                            }
                        />
                    </label>
                </>
            )}

            <Button
                variant="ghost"
                onClick={() =>
                    onChange({
                        platform: null,
                        region: null,
                        min_price: null,
                        max_price: null,
                        sort: 'popularity',
                        page: 1,
                    })
                }
            >
                Reset filters
            </Button>
        </form>
    )
}
