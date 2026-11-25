import { ChevronDown, Search, ShoppingCart, User } from 'lucide-react'
import type { Category } from '../../types'
import CategoryBar from './CategoryBar'

interface NavbarProps {
    query: string
    onQueryChange: (value: string) => void
    activeCategory: Category
    onCategoryChange: (category: Category) => void
    cartCount: number
    onOpenCart: () => void
}

export default function Navbar({
    query,
    onQueryChange,
    activeCategory,
    onCategoryChange,
    cartCount,
    onOpenCart,
}: NavbarProps) {
    return (
        <header className="sticky top-0 z-40 border-b border-white/10 bg-midnight/95 backdrop-blur-md">
            <div className="mx-auto max-w-[1280px] px-4">
                <div className="flex min-h-16 flex-wrap items-center gap-3 py-3">
                    <a
                        href="#top"
                        className="flex shrink-0 items-center gap-2"
                        aria-label="Eneba home"
                    >
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-sun text-lg font-black text-midnight">
                            E
                        </span>
                        <span className="text-xl font-black tracking-tight">
                            ENE<span className="text-sun">BA</span>
                        </span>
                    </a>

                    <div className="order-3 w-full md:order-none md:mx-auto md:max-w-xl md:flex-1">
                        <label className="relative block">
                            <span className="sr-only">Search games</span>
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-lavender"
                                aria-hidden="true"
                            />
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => onQueryChange(event.target.value)}
                                placeholder="Search games, gift cards and more"
                                className="w-full rounded-xl border border-white/10 bg-royal py-2.5 pr-4 pl-10 text-sm text-white placeholder:text-lavender/70 focus:border-sun focus:ring-2 focus:ring-sun/30 focus:outline-none"
                            />
                        </label>
                    </div>

                    <div className="ml-auto flex items-center gap-1.5 md:ml-0">
                        <a
                            href="/products"
                            className="hidden rounded-full border border-white/10 bg-royal px-3 py-2 text-xs font-bold text-lavender hover:bg-indigo-deep hover:text-white sm:block"
                        >
                            Full catalog
                        </a>

                        <span
                            className="hidden items-center gap-2 rounded-full border border-white/10 bg-royal px-3 py-2 text-xs font-bold text-white sm:flex"
                            aria-label="Language and currency: English, Euro"
                        >
                            EN <span className="text-lavender">|</span> EUR
                            <ChevronDown className="h-3.5 w-3.5 text-lavender" />
                        </span>

                        <a
                            href="/login"
                            aria-label="Account"
                            className="hidden h-10 w-10 place-items-center rounded-xl border border-white/10 text-lavender transition-colors hover:bg-indigo-deep hover:text-white sm:grid"
                        >
                            <User className="h-5 w-5" />
                        </a>

                        <button
                            type="button"
                            onClick={onOpenCart}
                            aria-label={`Open cart, ${cartCount} items`}
                            className="relative grid h-10 w-10 place-items-center rounded-xl bg-sun text-midnight transition-transform hover:scale-105"
                        >
                            <ShoppingCart className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute -top-1.5 -right-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-promo px-1 text-[11px] font-black text-white ring-2 ring-midnight">
                                    {cartCount}
                                </span>
                            )}
                        </button>
                    </div>
                </div>
            </div>

            <CategoryBar active={activeCategory} onChange={onCategoryChange} />
        </header>
    )
}
