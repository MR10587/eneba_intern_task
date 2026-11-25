import type {
    AuthUser,
    Cart,
    CartMeta,
    HomeBlock,
    Offer,
    Order,
    ProductDetail,
    ProductSummary,
} from '../types/api'

/** Minimal Response-shaped object so tests do not depend on jsdom fetch globals. */
export function jsonResponse(body: unknown, status = 200): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response
}

export function product(overrides: Partial<ProductSummary> = {}): ProductSummary {
    return {
        id: 1,
        slug: 'minecraft',
        name: 'Minecraft',
        description: 'Build anything.',
        platform: 'Steam',
        category: 'Games',
        genre: 'Sandbox',
        release_date: '2011-11-18',
        image_path: null,
        popularity: 980,
        min_price_cents: 1999,
        compare_at_price_cents: 2999,
        discount_percent: 33,
        currency: 'EUR',
        available: true,
        available_offer_count: 3,
        ...overrides,
    }
}

export function bannerBlock(overrides: Partial<HomeBlock> = {}): HomeBlock {
    return {
        key: 'home-banner',
        title: 'Featured game',
        type: 'banner',
        position: 0,
        data: {
            headline: 'Featured this week',
            subheadline: 'A great price',
            cta_label: 'Buy now',
            cta_href: '/products/minecraft',
            product: product(),
        },
        ...overrides,
    }
}

export function gridBlock(overrides: Partial<HomeBlock> = {}): HomeBlock {
    return {
        key: 'best-sellers',
        title: 'Best sellers',
        type: 'best_sellers',
        position: 1,
        data: [product()],
        ...overrides,
    }
}

export function offer(overrides: Partial<Offer> = {}): Offer {
    return {
        id: 10,
        seller: { id: 2, name: 'KeyKingdom' },
        region: 'Europe',
        price_cents: 1999,
        currency: 'EUR',
        available: true,
        ...overrides,
    }
}

export function productDetail(overrides: Partial<ProductDetail> = {}): ProductDetail {
    return {
        ...product(),
        offers: [offer()],
        ...overrides,
    }
}

export function authUser(overrides: Partial<AuthUser> = {}): AuthUser {
    return {
        id: 1,
        name: 'Ada',
        email: 'ada@example.com',
        email_verified_at: null,
        created_at: '2026-11-24T21:00:00.000000Z',
        updated_at: '2026-11-24T21:00:00.000000Z',
        ...overrides,
    }
}

export function cartResponse(
    overrides: Partial<Cart> = {},
    meta: CartMeta = { item_count: 1, available_item_count: 1 },
): { data: Cart; meta: CartMeta } {
    const item = offer()
    const summary = product()

    return {
        data: {
            items: [
                {
                    id: 1,
                    product_id: summary.id,
                    offer_id: item.id,
                    quantity: 1,
                    product: summary,
                    offer: item,
                    line_total_cents: item.price_cents,
                    currency: 'EUR',
                    available: true,
                },
            ],
            total_cents: item.price_cents,
            currency: 'EUR',
            ...overrides,
        },
        meta,
    }
}

export function orderResponse(overrides: Partial<Order> = {}): { data: Order } {
    return {
        data: {
            id: 7,
            order_id: 7,
            status: 'completed',
            total_cents: 1999,
            currency: 'EUR',
            payment_status: 'succeeded',
            payment_reference: 'SIM-TEST-4242',
            created_at: '2026-11-24T21:30:00.000000Z',
            items: [
                {
                    id: 12,
                    offer_id: 10,
                    product_id: 1,
                    product: { id: 1, slug: 'minecraft', name: 'Minecraft' },
                    region: 'Europe',
                    quantity: 1,
                    unit_price_cents: 1999,
                    line_total_cents: 1999,
                    digital_key: { code: 'KEY-ABCD-EFGH-IJKL' },
                },
            ],
            ...overrides,
        },
    }
}
