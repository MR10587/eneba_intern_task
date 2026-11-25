import { ShoppingBag, Trash2, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/authContext'
import type { Cart, CartMeta } from '../../types/api'
import { coverColors } from '../../utils/coverColors'
import { formatPrice } from '../../utils/format'
import CoverArt from '../ui/CoverArt'

interface CartResponse {
    data: Cart
    meta: CartMeta
}

interface CartDrawerProps {
    open: boolean
    cart: CartResponse | null
    loading: boolean
    error: Error | null
    onClose: () => void
    onRemove: (offerId: number) => Promise<void>
    onRetry: () => void
}

export default function CartDrawer({
    open,
    cart,
    loading,
    error,
    onClose,
    onRemove,
    onRetry,
}: CartDrawerProps) {
    const { user } = useAuth()
    const dialogRef = useRef<HTMLElement>(null)
    const closeButtonRef = useRef<HTMLButtonElement>(null)
    const onCloseRef = useRef(onClose)
    const [busyOfferId, setBusyOfferId] = useState<number | null>(null)

    useEffect(() => {
        onCloseRef.current = onClose
    }, [onClose])

    useEffect(() => {
        if (!open) {
            return
        }

        const previouslyFocused = document.activeElement as HTMLElement | null
        const previousOverflow = document.body.style.overflow
        const focusableSelector =
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

        document.body.style.overflow = 'hidden'
        closeButtonRef.current?.focus()

        function handleKey(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.preventDefault()
                onCloseRef.current()

                return
            }

            if (event.key !== 'Tab') {
                return
            }

            const focusable = dialogRef.current?.querySelectorAll<HTMLElement>(focusableSelector)

            if (!focusable || focusable.length === 0) {
                event.preventDefault()

                return
            }

            const first = focusable[0]
            const last = focusable[focusable.length - 1]

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault()
                last.focus()
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault()
                first.focus()
            }
        }

        document.addEventListener('keydown', handleKey)

        return () => {
            document.removeEventListener('keydown', handleKey)
            document.body.style.overflow = previousOverflow
            previouslyFocused?.focus()
        }
    }, [open])

    async function remove(offerId: number): Promise<void> {
        setBusyOfferId(offerId)

        try {
            await onRemove(offerId)
        } finally {
            setBusyOfferId(null)
        }
    }

    if (!open) {
        return null
    }

    const items = cart?.data.items ?? []
    const unavailableNames = items
        .filter((item) => !item.available)
        .map((item) => item.product.name)
    const checkoutBlocked = unavailableNames.length > 0

    return (
        <div className="fixed inset-0 z-50 flex justify-end">
            <button
                type="button"
                aria-label="Close cart"
                onClick={onClose}
                className="absolute inset-0 bg-black/60 backdrop-blur-sm"
            />

            <aside
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-label="Shopping cart"
                className="relative flex h-full w-full max-w-md flex-col border-l border-white/10 bg-midnight shadow-2xl"
            >
                <header className="flex items-center justify-between border-b border-white/10 px-5 py-4">
                    <div className="flex items-center gap-2">
                        <ShoppingBag className="h-5 w-5 text-sun" />
                        <h2 className="text-lg font-black text-white">Your cart</h2>
                        <span className="rounded-full bg-royal px-2 py-0.5 text-xs text-lavender">
                            {cart?.meta.item_count ?? 0}
                        </span>
                    </div>
                    <button
                        ref={closeButtonRef}
                        type="button"
                        onClick={onClose}
                        aria-label="Close cart drawer"
                        className="grid h-9 w-9 place-items-center rounded-lg text-lavender hover:bg-indigo-deep hover:text-white"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </header>

                <div className="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    {loading && (
                        <p className="mt-16 text-center text-sm text-lavender">Loading cart…</p>
                    )}

                    {error && (
                        <div role="alert" className="mt-16 text-center">
                            <p className="text-sm text-promo">{error.message}</p>
                            <button
                                type="button"
                                onClick={onRetry}
                                className="mt-3 rounded-xl border border-white/15 px-4 py-2 text-xs font-bold text-white hover:bg-white/10"
                            >
                                Retry
                            </button>
                        </div>
                    )}

                    {!loading && !error && items.length === 0 && (
                        <p className="mt-16 text-center text-sm text-lavender">
                            Your cart is empty. Add a game to get started.
                        </p>
                    )}

                    {!loading && !error && checkoutBlocked && (
                        <p role="alert" className="rounded-xl bg-promo/10 p-3 text-xs text-promo">
                            Unavailable: {unavailableNames.join(', ')}. Remove these items before
                            checkout.
                        </p>
                    )}

                    {!loading &&
                        !error &&
                        items.map((item) => (
                            <article
                                key={item.id}
                                className="flex gap-3 rounded-xl border border-white/10 bg-royal p-3"
                            >
                                <CoverArt
                                    title={item.product.name}
                                    colors={coverColors(item.product.slug)}
                                    className="h-24 w-16 shrink-0 rounded-lg"
                                />
                                <div className="min-w-0 flex-1">
                                    <h3 className="line-clamp-2 text-sm font-bold text-white">
                                        {item.product.name}
                                    </h3>
                                    <p className="mt-0.5 text-xs text-lavender">
                                        {item.offer.seller.name} · {item.offer.region} · Qty{' '}
                                        {item.quantity}
                                    </p>
                                    <p
                                        className={`mt-1 text-xs font-bold ${
                                            item.available ? 'text-trust' : 'text-promo'
                                        }`}
                                    >
                                        {item.available ? 'Available' : 'Unavailable'}
                                    </p>
                                    <div className="mt-2 flex items-center gap-2">
                                        <p className="text-sm font-black text-sun">
                                            {formatPrice(item.line_total_cents, item.currency)}
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => void remove(item.offer_id)}
                                            disabled={busyOfferId !== null}
                                            aria-label={`Remove ${item.product.name}`}
                                            className="ml-auto p-1.5 text-lavender hover:text-promo disabled:opacity-40"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        ))}
                </div>

                <footer className="border-t border-white/10 bg-royal px-5 py-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-lavender">Total</span>
                        <span className="text-2xl font-black text-white">
                            {formatPrice(cart?.data.total_cents, cart?.data.currency)}
                        </span>
                    </div>
                    {items.length > 0 && !checkoutBlocked ? (
                        <Link
                            to="/checkout"
                            onClick={onClose}
                            className="mt-4 block rounded-xl bg-sun py-3 text-center text-sm font-black text-midnight"
                        >
                            Checkout
                        </Link>
                    ) : (
                        <button
                            type="button"
                            disabled
                            className="mt-4 w-full rounded-xl bg-sun py-3 text-sm font-black text-midnight disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Checkout
                        </button>
                    )}
                    {!user && items.length > 0 && (
                        <p className="mt-2 text-center text-[11px] text-sun">
                            Sign in at checkout to place your order.
                        </p>
                    )}
                    <p className="mt-2 text-center text-[11px] text-lavender/70">
                        Demo checkout — no payment is processed.
                    </p>
                </footer>
            </aside>
        </div>
    )
}
