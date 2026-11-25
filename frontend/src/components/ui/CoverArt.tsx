interface CoverArtProps {
    title: string
    colors: [string, string]
    className?: string
}

/**
 * Generated cover placeholder. It never fetches an external image, so the app
 * cannot show broken links. Swap this component for <img> when real artwork
 * is available from the API.
 */
export default function CoverArt({ title, colors, className = '' }: CoverArtProps) {
    return (
        <div
            className={`relative isolate overflow-hidden ${className}`}
            style={{ background: `linear-gradient(145deg, ${colors[0]}, ${colors[1]})` }}
            role="img"
            aria-label={`${title} cover art placeholder`}
        >
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(255,255,255,0.28),transparent_55%)]" />
            <div className="absolute -right-8 -bottom-10 h-32 w-32 rounded-full bg-white/10 blur-2xl" />
            <div className="absolute inset-x-3 bottom-3 z-10">
                <p className="text-[11px] font-semibold tracking-[0.18em] text-white/70 uppercase">
                    Eneba
                </p>
                <p className="line-clamp-3 text-lg leading-tight font-extrabold text-white drop-shadow-lg">
                    {title}
                </p>
            </div>
        </div>
    )
}
