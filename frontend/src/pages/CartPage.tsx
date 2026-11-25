import { useState } from 'react'
import { Link } from 'react-router-dom'
import { getCart, removeCartItem } from '../api/endpoints'
import CoverArt from '../components/ui/CoverArt'
import Spinner from '../components/ui/Spinner'
import { useAuth } from '../context/authContext'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'
import { formatPrice } from '../utils/format'

export default function CartPage() {
    const { user } = useAuth()
    const cart = useAsync(() => getCart(), [])
    const [busyOfferId, setBusyOfferId] = useState<number | null>(null)
    const [error, setError] = useState<string | null>(null)

    async function remove(offerId: number): Promise<void> {
        setBusyOfferId(offerId)
        setError(null)

        try {
            await removeCartItem(offerId)
            cart.reload()
        } catch (caught: unknown) {
            setError(apiErrorMessage(caught))
        } finally {
            setBusyOfferId(null)
        }
    }

    if (cart.loading) {
        return <Spinner label="Loading cart" />
    }

    if (cart.error || !cart.data) {
        return (
            <div className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center">
                <h1 className="text-lg font-bold text-white">Cart unavailable</h1>
                <p className="mt-1 text-sm text-lavender">
                    {cart.error ? apiErrorMessage(cart.error) : 'No cart was returned.'}
                </p>
            </div>
        )
    }

    const data = cart.data.data
    const meta = cart.data.meta
    const currency = data.currency
    const total = formatPrice(data.total_cents, currency)
    const unavailableNames = data.items
        .filter((item) => !item.available)
        .map((item) => item.product.name)
    const checkoutBlocked = unavailableNames.length > 0

    return (
        <div className="space-y-6">
            <header>
                <p className="text-xs font-black tracking-widest text-sun uppercase">Your cart</p>
                <h1 className="mt-1 text-3xl font-black text-white">{meta.item_count} items</h1>
                {checkoutBlocked && (
                    <p role="alert" className="mt-2 rounded-xl bg-promo/10 p-3 text-sm text-promo">
                        Unavailable: {unavailableNames.join(', ')}. Remove these items before
                        checkout.
                    </p>
                )}
            </header>

            {error && (
                <p role="alert" className="rounded-xl bg-promo/10 p-3 text-sm text-promo">
                    {error}
                </p>
            )}

            {data.items.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-white/20 p-10 text-center">
                    <h2 className="text-lg font-bold text-white">Your cart is empty</h2>
                    <p className="mt-1 text-sm text-lavender">
                        Find a game or gift card to get started.
                    </p>
                    <Link
                        className="mt-4 inline-block rounded-xl bg-sun px-4 py-2 text-sm font-black text-midnight"
                        to="/products"
                    >
                        Browse the store
                    </Link>
                </div>
            ) : (
                <>
                    <div className="space-y-3">
                        {data.items.map((item) => (
                            <article
                                key={item.id}
                                className="flex flex-wrap items-center gap-4 rounded-2xl border border-white/10 bg-royal p-4"
                            >
                                <CoverArt
                                    title={item.product.name}
                                    colors={['#381880', '#1f0a4d']}
                                    className="h-24 w-16 shrink-0 rounded-lg"
                                />

                                <div className="min-w-0 flex-1">
                                    <h2 className="truncate font-bold text-white">
                                        {item.product.name}
                                    </h2>
                                    <p className="mt-0.5 text-xs text-lavender">
                                        {item.offer.seller.name} · {item.offer.region} · Qty{' '}
                                        {item.quantity}
                                    </p>
                                    <p
                                        className={`mt-1 text-xs font-bold ${item.available ? 'text-trust' : 'text-promo'}`}
                                    >
                                        {item.available ? 'Available' : 'Unavailable'}
                                    </p>
                                </div>

                                <p className="text-lg font-black text-white">
                                    {formatPrice(item.line_total_cents, item.currency)}
                                </p>

                                <button
                                    type="button"
                                    disabled={busyOfferId !== null}
                                    onClick={() => void remove(item.offer_id)}
                                    className="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-lavender hover:border-promo hover:text-promo disabled:opacity-40"
                                >
                                    {busyOfferId === item.offer_id ? 'Removing…' : 'Remove'}
                                </button>
                            </article>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-royal p-5">
                        <div>
                            <p className="text-sm text-lavender">Order total</p>
                            <p className="text-2xl font-black text-white">{total}</p>
                            {!user && (
                                <p className="mt-1 text-xs text-sun">
                                    Sign in at checkout to place your order.
                                </p>
                            )}
                        </div>
                        {checkoutBlocked ? (
                            <button
                                type="button"
                                disabled
                                aria-disabled="true"
                                className="cursor-not-allowed rounded-xl bg-sun px-5 py-3 text-sm font-black text-midnight opacity-40"
                            >
                                Continue to checkout
                            </button>
                        ) : (
                            <Link
                                to="/checkout"
                                className="rounded-xl bg-sun px-5 py-3 text-sm font-black text-midnight"
                            >
                                Continue to checkout
                            </Link>
                        )}
                    </div>
                </>
            )}
        </div>
    )
}
