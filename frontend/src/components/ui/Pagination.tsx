import { Link } from 'react-router-dom'
import type { PaginationMeta } from '../../types/api'

interface PaginationProps {
    meta: PaginationMeta
    hrefForPage: (page: number) => string
}

export default function Pagination({ meta, hrefForPage }: PaginationProps) {
    if (meta.last_page <= 1) {
        return null
    }

    const pages = Array.from({ length: meta.last_page }, (_, index) => index + 1)

    return (
        <nav className="pagination" aria-label="Pagination">
            {meta.current_page > 1 ? (
                <Link className="pagination__step" to={hrefForPage(meta.current_page - 1)}>
                    Previous
                </Link>
            ) : (
                <span className="pagination__step pagination__step--disabled">Previous</span>
            )}

            <ul className="pagination__pages">
                {pages.map((page) => (
                    <li key={page}>
                        <Link
                            className={`pagination__page ${
                                page === meta.current_page ? 'pagination__page--active' : ''
                            }`.trim()}
                            to={hrefForPage(page)}
                            aria-current={page === meta.current_page ? 'page' : undefined}
                        >
                            {page}
                        </Link>
                    </li>
                ))}
            </ul>

            {meta.current_page < meta.last_page ? (
                <Link className="pagination__step" to={hrefForPage(meta.current_page + 1)}>
                    Next
                </Link>
            ) : (
                <span className="pagination__step pagination__step--disabled">Next</span>
            )}

            <p className="pagination__summary">
                {meta.from === null
                    ? 'No results'
                    : `Showing ${meta.from}–${meta.to} of ${meta.total}`}
            </p>
        </nav>
    )
}
