export interface PaginationMeta {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
}

export interface Paginated<T, TMeta extends { pagination: PaginationMeta }> {
    data: T[]
    meta: TMeta
}

export interface ApiErrorPayload {
    message?: string
    code?: string
    errors?: Record<string, string[]>
}

export type SortOption = 'price_asc' | 'price_desc' | 'popularity' | 'relevance'

export interface Seller {
    id: number
    name: string
}

export interface ProductSummary {
    id: number
    slug: string
    name: string
    description: string | null
    platform: string
    category: string
    genre: string | null
    release_date: string | null
    image_path: string | null
    popularity: number
    min_price_cents: number | null
    compare_at_price_cents: number | null
    discount_percent: number | null
    currency: string
    available: boolean
    available_offer_count: number
}

export interface Offer {
    id: number
    seller: Seller
    region: string
    price_cents: number
    currency: string
    available: boolean
}

export interface ProductDetail extends ProductSummary {
    offers: Offer[]
}

export interface CatalogFilters {
    categories: string[]
    platforms: string[]
    regions: string[]
    price_range: {
        min_cents: number | null
        max_cents: number | null
    }
    currency: string
}

export interface AppliedFilters {
    category: string | null
    platform: string | null
    region: string | null
    min_price_cents: number | null
    max_price_cents: number | null
    sort: SortOption
    page: number
    per_page: number
}

export interface ProductListMeta {
    pagination: PaginationMeta
    applied_filters: AppliedFilters
}

export interface SearchMeta {
    query: string
    result_count: number
    applied_filters: AppliedFilters
    pagination: PaginationMeta
}

export interface BannerBlockData {
    headline: string | null
    subheadline: string | null
    cta_label: string | null
    cta_href: string | null
    product: ProductSummary | null
}

export interface HomeBlock {
    key: string
    title: string
    type: string
    position: number
    data: unknown
}

export interface HomeResponse {
    data: HomeBlock[]
    meta: {
        block_count: number
    }
}

export interface AuthUser {
    id: number
    name: string
    email: string
    email_verified_at: string | null
    created_at: string
    updated_at: string
}

export interface AuthSession {
    user: AuthUser
    token: string
    token_type: string
}

export interface CartItem {
    id: number
    product_id: number
    offer_id: number
    quantity: number
    product: ProductSummary
    offer: Offer
    line_total_cents: number
    currency: string
    available: boolean
}

export interface Cart {
    items: CartItem[]
    total_cents: number
    currency: string
}

export interface CartMeta {
    item_count: number
    available_item_count: number
    // Issued for guests so later requests can name the same server-side cart.
    cart_token?: string
}

export interface OrderItem {
    id: number
    offer_id: number
    product_id: number
    product: Pick<ProductSummary, 'id' | 'slug' | 'name'>
    region: string
    quantity: number
    unit_price_cents: number
    line_total_cents: number
    digital_key?: {
        code: string
    }
}

export interface Order {
    id: number
    order_id: number
    status: string
    total_cents: number
    currency: string
    payment_status: string
    payment_reference: string
    created_at: string
    items: OrderItem[]
}

export interface CheckoutMeta {
    keys_delivered: number
}
