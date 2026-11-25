import type { BannerBlockData, ProductSummary } from './api'

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export function isBannerBlockData(value: unknown): value is BannerBlockData {
    if (!isRecord(value) || !('product' in value)) {
        return false
    }

    return (
        value.product === null ||
        (isRecord(value.product) &&
            typeof value.product.id === 'number' &&
            typeof value.product.slug === 'string' &&
            typeof value.product.name === 'string')
    )
}

export function isProductSummaryArray(value: unknown): value is ProductSummary[] {
    return (
        Array.isArray(value) &&
        value.every(
            (item) =>
                isRecord(item) &&
                typeof item.id === 'number' &&
                typeof item.slug === 'string' &&
                typeof item.name === 'string',
        )
    )
}
