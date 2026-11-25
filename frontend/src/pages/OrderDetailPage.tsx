import { Link, useLocation, useParams } from 'react-router-dom'
import { getOrder } from '../api/endpoints'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'
import { formatDate, formatPrice } from '../utils/format'

export default function OrderDetailPage() {
    const { id = '' } = useParams()
    const location = useLocation()
    const order = useAsync(() => getOrder(Number(id)), [id])
    const justPlaced = Boolean((location.state as { justPlaced?: boolean } | null)?.justPlaced)

    if (order.loading) {
        return <Spinner label="Loading order" />
    }

    if (order.error || !order.data) {
        return (
            <div className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center">
                <h1 className="text-lg font-bold text-white">Order unavailable</h1>
                <p className="mt-1 text-sm text-lavender">
                    {order.error ? apiErrorMessage(order.error) : 'No order was returned.'}
                </p>
                <Link className="mt-4 inline-block font-bold text-sun hover:underline" to="/orders">
                    Back to order history
                </Link>
            </div>
        )
    }

    const data = order.data.data

    return (
        <div className="space-y-6">
            {justPlaced && (
                <div className="rounded-2xl border border-trust/40 bg-trust/10 p-5">
                    <h1 className="text-xl font-black text-trust">Payment succeeded</h1>
                    <p className="mt-1 text-sm text-lavender">
                        Your keys are ready below. A simulated payment was used — no real charge was
                        made.
                    </p>
                </div>
            )}

            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-black tracking-widest text-sun uppercase">Order</p>
                    <h1 className="mt-1 text-3xl font-black text-white">Order #{data.order_id}</h1>
                    <p className="mt-1 text-sm text-lavender">
                        {formatDate(data.created_at)} · {data.payment_reference}
                    </p>
                </div>
                <p className="text-2xl font-black text-white">
                    {formatPrice(data.total_cents, data.currency)}
                </p>
            </header>

            <div className="space-y-3">
                {data.items.map((item) => (
                    <article
                        key={item.id}
                        className="rounded-2xl border border-white/10 bg-royal p-5"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="font-bold text-white">{item.product.name}</h2>
                                <p className="mt-0.5 text-xs text-lavender">
                                    {item.region} · Qty {item.quantity}
                                </p>
                            </div>
                            <p className="font-black text-white">
                                {formatPrice(item.line_total_cents, data.currency)}
                            </p>
                        </div>

                        {item.digital_key ? (
                            <p className="mt-4 rounded-xl border border-trust/30 bg-trust/10 p-3 font-mono text-sm font-bold text-trust">
                                {item.digital_key.code}
                            </p>
                        ) : (
                            <p className="mt-4 text-xs text-lavender">
                                Key not included in this response.
                            </p>
                        )}
                    </article>
                ))}
            </div>

            <Link className="inline-block font-bold text-sun hover:underline" to="/orders">
                ← Back to order history
            </Link>
        </div>
    )
}
