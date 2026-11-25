import { formatPrice } from '../../utils/format'

interface PriceProps {
    cents: number | null
    currency: string
    compareAt?: number | null
}

export default function Price({ cents, currency, compareAt = null }: PriceProps) {
    return (
        <span className="price">
            <span className="price__current">{formatPrice(cents, currency)}</span>
            {compareAt !== null && cents !== null && compareAt > cents && (
                <s className="price__compare">{formatPrice(compareAt, currency)}</s>
            )}
        </span>
    )
}
