import { getHome } from '../api/endpoints'
import HomeBlockRenderer from '../components/catalog/HomeBlockRenderer'
import ErrorState from '../components/ui/ErrorState'
import Spinner from '../components/ui/Spinner'
import { useAsync } from '../hooks/useAsync'

export default function HomePage() {
    const { data, error, loading, reload } = useAsync(() => getHome(), [])

    return (
        <div className="home-page">
            <section className="hero-copy">
                <p className="eyebrow">Digital game keys</p>
                <h1>Browse. Compare. Play.</h1>
                <p>Browse the latest releases, gift cards, top-ups, and gear in one place.</p>
            </section>

            {loading && <Spinner label="Loading homepage" />}

            {error && <ErrorState message={error.message} onRetry={reload} />}

            {data &&
                data.data.map((block) => (
                    <HomeBlockRenderer key={`${block.key}-${block.position}`} block={block} />
                ))}

            {data && data.meta.block_count === 0 && (
                <p className="muted">No homepage blocks are enabled right now.</p>
            )}
        </div>
    )
}
