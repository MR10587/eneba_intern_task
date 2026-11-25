import { describe, expect, it } from 'vitest'
import { formatDate, formatPrice } from './format'

describe('formatPrice', () => {
    it('formats integer cents without recalculating a price', () => {
        expect(formatPrice(1999)).toBe('€19.99')
        expect(formatPrice(499, 'EUR')).toBe('€4.99')
    })

    it('has an explicit unavailable state', () => {
        expect(formatPrice(null)).toBe('Unavailable')
        expect(formatPrice(undefined)).toBe('Unavailable')
    })
})

describe('formatDate', () => {
    it('formats backend timestamps for display', () => {
        expect(formatDate('2026-11-24T12:00:00.000000Z')).toMatch(/Nov/)
    })

    it('keeps missing values visually explicit', () => {
        expect(formatDate(null)).toBe('—')
    })
})
