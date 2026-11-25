import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'

export default function Header() {
    const navigate = useNavigate()
    const [query, setQuery] = useState('')
    const canSearch = query.trim().length >= 2

    function handleSubmit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault()

        if (!canSearch) {
            return
        }

        navigate(`/search?q=${encodeURIComponent(query.trim())}`)
    }

    return (
        <header className="site-header">
            <div className="site-header__inner">
                <Link className="brand" to="/" aria-label="Eneba home">
                    ENE<span className="brand__dot">BA</span>
                </Link>

                <nav className="site-nav" aria-label="Main navigation">
                    <Link to="/">Home</Link>
                    <Link to="/products">Store</Link>
                </nav>

                <form className="search-bar" role="search" onSubmit={handleSubmit}>
                    <label className="sr-only" htmlFor="global-search">
                        Search games
                    </label>
                    <input
                        id="global-search"
                        name="q"
                        type="search"
                        placeholder="Search games"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        minLength={2}
                    />
                    <button type="submit" disabled={!canSearch}>
                        Search
                    </button>
                </form>
            </div>
        </header>
    )
}
