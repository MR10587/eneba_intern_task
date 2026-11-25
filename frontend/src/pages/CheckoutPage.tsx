import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { checkout, getCart } from '../api/endpoints'
import PaymentForm from '../components/checkout/PaymentForm'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'
import { formatPrice } from '../utils/format'

export default function CheckoutPage() {
    const navigate = useNavigate()
    const cart = useAsync(() => getCart(), [])
    const [submitting, setSubmitting] = useState(false)
    const [error, setError] = useState<string | null>(null)

    async function pay(cardNumber: string): Promise<void> {
        setSubmitting(true)
        setError(null)

        try {
            const { data } = await checkout(cardNumber)

            navigate(`/orders/${data.id}`, { replace: true, state: { justPlaced: true } })
        } catch (caught: unknown) {
            setError(apiErrorMessage(caught))
        } finally {
            setSubmitting(false)
        }
    }

    if (cart.loading) {
        return <Spinner label="Loading checkout summary" />
    }

    if (cart.error || !cart.data) {
        return (
            <div className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center">
                <h1 className="text-lg font-bold text-white">Checkout unavailable</h1>
                <p className="mt-1 text-sm text-lavender">
                    {cart.error ? apiErrorMessage(cart.error) : 'No cart was returned.'}
                </p>
            </div>
        )
    }

    const data = cart.data.data
    const total = formatPrice(data.total_cents, data.currency)
    const hasUnavailableItems = data.items.some((item) => !item.available)

    // WHY: the backend will reject a stale cart at payment time. Send the buyer
    // back to the cart, where the unavailable lines are named and removable,
    // instead of offering a checkout form that cannot succeed.
    if (hasUnavailableItems) {
        return <Navigate to="/cart" replace />
    }

    if (data.items.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-white/20 p-10 text-center">
                <h1 className="text-lg font-bold text-white">Nothing to check out</h1>
                <p className="mt-1 text-sm text-lavender">Add an offer to your cart first.</p>
                <Link
                    className="mt-4 inline-block rounded-xl bg-sun px-4 py-2 text-sm font-black text-midnight"
                    to="/products"
                >
                    Browse the store
                </Link>
            </div>
        )
    }

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <section className="rounded-2xl border border-white/10 bg-royal p-6">
                <p className="text-xs font-black tracking-widest text-sun uppercase">
                    Order summary
                </p>
                <h1 className="mt-1 text-2xl font-black text-white">Review your items</h1>

                <ul className="mt-5 space-y-3">
                    {data.items.map((item) => (
                        <li
                            key={item.id}
                            className="flex justify-between gap-4 border-b border-white/10 pb-3"
                        >
                            <span className="text-sm text-white">
                                {item.product.name}
                                <span className="block text-xs text-lavender">
                                    {item.offer.region}
                                </span>
                            </span>
                            <span className="text-sm font-bold text-white">
                                {formatPrice(item.line_total_cents, item.currency)}
                            </span>
                        </li>
                    ))}
                </ul>

                <div className="mt-5 flex justify-between text-lg font-black text-white">
                    <span>Total</span>
                    <span>{total}</span>
                </div>
            </section>

            <PaymentForm
                totalLabel={total}
                submitting={submitting}
                error={error}
                onSubmit={(cardNumber) => void pay(cardNumber)}
            />
        </div>
    )
}
