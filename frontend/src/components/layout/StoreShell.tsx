import { LogOut, ShoppingCart, User } from 'lucide-react'
import { useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getCart } from '../../api/endpoints'
import { useAuth } from '../../context/authContext'
import { useAsync } from '../../hooks/useAsync'

const currentYear = new Date().getFullYear()

export default function StoreShell({ children }: { children: ReactNode }) {
    const { user, token, logout } = useAuth()
    const navigate = useNavigate()
    const [query, setQuery] = useState('')
    const cart = useAsync(() => getCart(), [token])
    const cartCount = cart.data?.meta.item_count ?? 0

    function handleSearch(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault()

        if (query.trim().length >= 2) {
            navigate(`/search?q=${encodeURIComponent(query.trim())}`)
        }
    }

    async function handleLogout(): Promise<void> {
        await logout()
        navigate('/')
    }

    return (
        <div className="min-h-screen bg-midnight text-white">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-midnight/95 backdrop-blur-md">
                <div className="mx-auto flex max-w-[1280px] flex-wrap items-center gap-3 px-4 py-3">
                    <Link
                        className="flex shrink-0 items-center gap-2"
                        to="/"
                        aria-label="Eneba home"
                    >
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-sun text-lg font-black text-midnight">
                            E
                        </span>
                        <span className="text-xl font-black tracking-tight">
                            ENE<span className="text-sun">BA</span>
                        </span>
                    </Link>

                    <form
                        className="order-3 w-full md:order-none md:mx-auto md:max-w-xl md:flex-1"
                        onSubmit={handleSearch}
                        role="search"
                    >
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search games"
                            aria-label="Search games"
                            className="w-full rounded-xl border border-white/10 bg-royal px-4 py-2.5 text-sm text-white placeholder:text-lavender/70 focus:border-sun focus:outline-none"
                        />
                    </form>

                    <div className="ml-auto flex items-center gap-2 md:ml-0">
                        <Link
                            to="/cart"
                            aria-label={`Cart, ${cartCount} items`}
                            className="relative grid h-10 w-10 place-items-center rounded-xl bg-sun text-midnight"
                        >
                            <ShoppingCart className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute -top-1.5 -right-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-promo px-1 text-[11px] font-black text-white ring-2 ring-midnight">
                                    {cartCount}
                                </span>
                            )}
                        </Link>

                        {user ? (
                            <>
                                <Link
                                    to="/orders"
                                    className="hidden items-center gap-2 rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-lavender hover:bg-indigo-deep hover:text-white sm:flex"
                                >
                                    <User className="h-4 w-4" />
                                    {user.name}
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => void handleLogout()}
                                    aria-label="Log out"
                                    className="grid h-10 w-10 place-items-center rounded-xl border border-white/10 text-lavender hover:bg-indigo-deep hover:text-white"
                                >
                                    <LogOut className="h-5 w-5" />
                                </button>
                            </>
                        ) : (
                            <Link
                                to="/login"
                                className="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-lavender hover:bg-indigo-deep hover:text-white"
                            >
                                Sign in
                            </Link>
                        )}
                    </div>
                </div>

                <nav
                    className="border-t border-white/10 bg-indigo-deep/60"
                    aria-label="Store sections"
                >
                    <div className="mx-auto flex max-w-[1280px] gap-1 overflow-x-auto px-4 py-2">
                        {[
                            { label: 'Live store', to: '/products' },
                            { label: 'Browse blocks', to: '/browse' },
                            { label: 'Cart', to: '/cart' },
                            { label: 'Orders', to: '/orders' },
                        ].map((item) => (
                            <Link
                                key={item.to}
                                to={item.to}
                                className="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold text-lavender hover:bg-white/10 hover:text-white"
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                </nav>
            </header>

            <main className="mx-auto max-w-[1280px] px-4 py-8">{children}</main>

            <footer className="mt-12 border-t border-white/10 bg-royal/60 py-6 text-center text-xs text-lavender/70">
                © {currentYear} Eneba. Demo storefront — no real payments.
            </footer>
        </div>
    )
}
