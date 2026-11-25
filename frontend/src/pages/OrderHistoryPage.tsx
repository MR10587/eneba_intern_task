import { Link } from 'react-router-dom'
import { getOrders } from '../api/endpoints'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'
import { apiErrorMessage } from '../utils/errors'
import { formatDate, formatPrice } from '../utils/format'

export default function OrderHistoryPage() {
    const orders = useAsync(() => getOrders(), [])

    if (orders.loading) {
        return <Spinner label="Loading orders" />
    }

    if (orders.error || !orders.data) {
        return (
            <div className="rounded-2xl border border-promo/40 bg-promo/10 p-6 text-center">
                <h1 className="text-lg font-bold text-white">Orders unavailable</h1>
                <p className="mt-1 text-sm text-lavender">
                    {orders.error ? apiErrorMessage(orders.error) : 'No orders were returned.'}
                </p>
            </div>
        )
    }

    return (
        <div className="space-y-6">
            <header>
                <p className="text-xs font-black tracking-widest text-sun uppercase">Account</p>
                <h1 className="mt-1 text-3xl font-black text-white">Order history</h1>
                <p className="mt-1 text-sm text-lavender">
                    Purchased products, order numbers, and dates.
                </p>
            </header>

            {orders.data.data.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-white/20 p-10 text-center">
                    <h2 className="text-lg font-bold text-white">No orders yet</h2>
                    <Link
                        className="mt-4 inline-block font-bold text-sun hover:underline"
                        to="/products"
                    >
                        Browse the store
                    </Link>
                </div>
            ) : (
                <div className="space-y-3">
                    {orders.data.data.map((order) => (
                        <article
                            key={order.id}
                            className="rounded-2xl border border-white/10 bg-royal p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 className="font-black text-white">
                                        Order #{order.order_id}
                                    </h2>
                                    <p className="mt-0.5 text-xs text-lavender">
                                        {formatDate(order.created_at)} · {order.payment_status}
                                    </p>
                                </div>
                                <p className="text-lg font-black text-white">
                                    {formatPrice(order.total_cents, order.currency)}
                                </p>
                            </div>

                            <ul className="mt-4 space-y-1 text-sm text-lavender">
                                {order.items.map((item) => (
                                    <li key={item.id}>
                                        {item.product.name} · {item.region}
                                    </li>
                                ))}
                            </ul>

                            <Link
                                className="mt-4 inline-block rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-sun hover:bg-indigo-deep"
                                to={`/orders/${order.id}`}
                            >
                                View keys and details
                            </Link>
                        </article>
                    ))}
                </div>
            )}
        </div>
    )
}
