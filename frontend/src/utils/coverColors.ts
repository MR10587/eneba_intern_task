const coverPairs: Array<[string, string]> = [
    ['#381880', '#1F0A4D'],
    ['#FF2A7A', '#6B1350'],
    ['#00D09C', '#0B5A57'],
    ['#FAD318', '#8A5A00'],
    ['#8A2BE2', '#2C1266'],
    ['#2E7D62', '#0C2A20'],
]

/** Deterministic presentation-only gradient derived from an API identifier. */
export function coverColors(key: string): [string, string] {
    let hash = 0

    for (const character of key) {
        hash = (hash * 31 + character.charCodeAt(0)) >>> 0
    }

    return coverPairs[hash % coverPairs.length]
}
