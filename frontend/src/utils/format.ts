const dateFormatter = new Intl.DateTimeFormat('en-IE', { dateStyle: 'medium' })

export function formatPrice(cents: number | null | undefined, currency = 'EUR'): string {
    if (cents === null || cents === undefined) {
        return 'Unavailable'
    }

    // WHY: the API owns all money values in integer cents. This function only
    // formats the backend value for display; it never derives a price.
    return new Intl.NumberFormat('en-IE', {
        style: 'currency',
        currency,
    }).format(cents / 100)
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    return Number.isNaN(date.getTime()) ? value : dateFormatter.format(date)
}
