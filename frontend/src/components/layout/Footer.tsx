import { Gamepad2 } from 'lucide-react'
import { categories } from '../../data/categories'
import type { Category } from '../../types'

const currentYear = new Date().getFullYear()

interface FooterProps {
    onCategoryChange?: (category: Category) => void
}

export default function Footer({ onCategoryChange }: FooterProps) {
    return (
        <footer className="mt-12 border-t border-white/10 bg-royal/60">
            <div className="mx-auto grid max-w-[1280px] gap-8 px-4 py-10 md:grid-cols-4">
                <div>
                    <p className="flex items-center gap-2 text-lg font-black">
                        <Gamepad2 className="h-5 w-5 text-sun" />
                        ENE<span className="text-sun">BA</span>
                    </p>
                    <p className="mt-2 max-w-xs text-sm text-lavender">
                        Discover games, gift cards, top-ups, and hardware with offers from trusted
                        sellers.
                    </p>
                </div>

                <div>
                    <h3 className="text-xs font-black tracking-widest text-white uppercase">
                        Categories
                    </h3>
                    <ul className="mt-3 space-y-2">
                        {categories.map((category) => (
                            <li key={category}>
                                <button
                                    type="button"
                                    onClick={() => onCategoryChange?.(category)}
                                    className="text-sm text-lavender hover:text-sun"
                                >
                                    {category}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>

                <div>
                    <h3 className="text-xs font-black tracking-widest text-white uppercase">
                        Explore
                    </h3>
                    <ul className="mt-3 space-y-2 text-sm text-lavender">
                        <li>
                            <a href="/browse" className="hover:text-sun">
                                Home blocks
                            </a>
                        </li>
                        <li>
                            <a href="/products" className="hover:text-sun">
                                Full catalog
                            </a>
                        </li>
                        <li>
                            <a href="/orders" className="hover:text-sun">
                                Order history
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 className="text-xs font-black tracking-widest text-white uppercase">
                        Trust
                    </h3>
                    <ul className="mt-3 space-y-2 text-sm text-lavender">
                        <li>Instant delivery</li>
                        <li>Secure checkout</li>
                        <li>Region info</li>
                    </ul>
                </div>
            </div>
            <div className="border-t border-white/10 py-4 text-center text-xs text-lavender/70">
                © {currentYear} Eneba. Demo storefront — no real payments.
            </div>
        </footer>
    )
}
