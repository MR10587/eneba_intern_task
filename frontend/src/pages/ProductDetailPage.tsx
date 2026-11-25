import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { addCartItem, getProductBySlug } from '../api/endpoints'
import OfferList from '../components/catalog/OfferList'
import CoverArt from '../components/ui/CoverArt'
import Spinner from '../components/ui/Spinner'
import { useToast } from '../context/toastContext'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'
import { formatDate, formatPrice } from '../utils/format'

export default function ProductDetailPage() {
    const { slug = '' } = useParams()
    const navigate = useNavigate()
    const { showToast } = useToast()
    const product = useAsync(() => getProductBySlug(slug), [slug])
    const [busyOfferId, setBusyOfferId] = useState<number | null>(null)
    const [notice, setNotice] = useState<string | null>(null)

    async function add(offerId: number): Promise<void> {
        if (!product.data) {
            return
        }

        setBusyOfferId(offerId)
        setNotice(null)

        try {
            await addCartItem({ product_id: product.data.data.id, offer_id: offerId })
            showToast(`${product.data.data.name} added to cart. Ready for checkout.`)
            navigate('/cart')
        } catch (error: unknown) {
            setNotice(apiErrorMessage(error))
        } finally {
            setBusyOfferId(null)
        }
    }

    if (product.loading) {
        return <Spinner label="Loading product" />
    }

    if (product.error || !product.data) {
        return (
            <div className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center">
                <h1 className="text-lg font-bold text-white">Product unavailable</h1>
                <p className="mt-1 text-sm text-lavender">
                    {product.error ? apiErrorMessage(product.error) : 'No product was returned.'}
                </p>
                <Link
                    className="mt-4 inline-block font-bold text-sun hover:underline"
                    to="/products"
                >
                    Back to store
                </Link>
            </div>
        )
    }

    const detail = product.data.data

    return (
        <div className="space-y-8">
            <Link className="text-sm font-semibold text-lavender hover:text-sun" to="/products">
                ← Back to store
            </Link>

            <section className="grid gap-6 md:grid-cols-[240px_minmax(0,1fr)]">
                <CoverArt
                    title={detail.name}
                    colors={['#381880', '#1f0a4d']}
                    className="aspect-[3/4] w-full max-w-[240px] rounded-2xl"
                />

                <div>
                    <p className="text-xs font-black tracking-widest text-sun uppercase">
                        {detail.platform}
                        {detail.genre ? ` · ${detail.genre}` : ''}
                    </p>
                    <h1 className="mt-1 text-3xl font-black text-white">{detail.name}</h1>
                    <p className="mt-2 text-sm text-lavender">
                        Released {formatDate(detail.release_date)}
                    </p>
                    <p className="mt-4 max-w-2xl text-sm leading-relaxed text-lavender">
                        {detail.description ?? 'No description available.'}
                    </p>
                    <p className="mt-4 text-2xl font-black text-white">
                        {formatPrice(detail.min_price_cents, detail.currency)}
                    </p>
                    <p className="mt-1 text-xs text-lavender">
                        {detail.available_offer_count} available offers
                    </p>
                </div>
            </section>

            <section aria-labelledby="offers-heading">
                <h2 id="offers-heading" className="mb-4 text-xl font-black text-white">
                    Seller offers
                </h2>

                {notice && (
                    <p role="alert" className="mb-4 rounded-xl bg-promo/10 p-3 text-sm text-promo">
                        {notice}
                    </p>
                )}

                <OfferList
                    offers={detail.offers}
                    busyOfferId={busyOfferId}
                    onAdd={(offerId) => void add(offerId)}
                />
            </section>
        </div>
    )
}
