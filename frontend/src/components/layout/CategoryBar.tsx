import { categories } from '../../data/categories'
import type { Category } from '../../types'

interface CategoryBarProps {
    active: Category
    onChange: (category: Category) => void
}

export default function CategoryBar({ active, onChange }: CategoryBarProps) {
    return (
        <nav className="border-t border-white/10 bg-indigo-deep/60" aria-label="Categories">
            <div className="mx-auto flex max-w-[1280px] gap-1 overflow-x-auto px-4 py-2">
                {categories.map((item) => (
                    <button
                        key={item}
                        type="button"
                        onClick={() => onChange(item)}
                        aria-pressed={active === item}
                        className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${
                            active === item
                                ? 'bg-sun text-midnight'
                                : 'text-lavender hover:bg-white/10 hover:text-white'
                        }`}
                    >
                        {item}
                    </button>
                ))}
            </div>
        </nav>
    )
}
