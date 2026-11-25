import type { Offer } from '../../types/api'
import { formatPrice } from '../../utils/format'

interface OfferListProps {
    offers: Offer[]
    busyOfferId: number | null
    onAdd: (offerId: number) => void
}

export default function OfferList({ offers, busyOfferId, onAdd }: OfferListProps) {
    return (
        <div className="space-y-3" aria-label="Seller offers">
            {offers.map((offer) => (
                <article
                    key={offer.id}
                    className="flex flex-wrap items-center gap-4 rounded-xl border border-white/10 bg-royal p-4"
                >
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-bold text-white">{offer.seller.name}</p>
                        <p className="mt-0.5 text-xs text-lavender">
                            {offer.region} · {offer.currency}
                        </p>
                    </div>

                    <div className="text-right">
                        <p className="text-lg font-black text-white">
                            {formatPrice(offer.price_cents, offer.currency)}
                        </p>
                        <p
                            className={`text-xs font-semibold ${offer.available ? 'text-trust' : 'text-promo'}`}
                        >
                            {offer.available ? 'Available' : 'Sold out'}
                        </p>
                    </div>

                    <button
                        type="button"
                        disabled={!offer.available || busyOfferId !== null}
                        onClick={() => onAdd(offer.id)}
                        className="rounded-xl bg-sun px-4 py-2 text-xs font-black text-midnight disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {busyOfferId === offer.id ? 'Adding…' : 'Add to cart'}
                    </button>
                </article>
            ))}
        </div>
    )
}
