import type {
    Cart,
    CartMeta,
    CatalogFilters,
    CheckoutMeta,
    HomeResponse,
    Order,
    PaginationMeta,
    ProductDetail,
    ProductListMeta,
    ProductSummary,
    SearchMeta,
    SortOption,
} from '../types/api'
import { ensureCartToken, getCartToken, setCartToken } from '../utils/cartToken'
import { apiFetch } from './client'

type QueryValue = string | number | null | undefined

export interface ProductQuery {
    category?: string | null
    platform?: string | null
    region?: string | null
    min_price?: number | null
    max_price?: number | null
    sort?: SortOption
    page?: number
    per_page?: number
}

export interface SearchQuery {
    q: string
    category?: string | null
    platform?: string | null
    region?: string | null
    min_price?: number | null
    max_price?: number | null
    page?: number
    per_page?: number
}

interface CartResponse {
    data: Cart
    meta: CartMeta
}

function cartHeaders(): HeadersInit {
    return { 'X-Cart-Token': ensureCartToken() }
}

function rememberCartToken<T extends { meta?: { cart_token?: string } }>(response: T): T {
    if (response.meta?.cart_token) {
        setCartToken(response.meta.cart_token)
    }

    return response
}

function queryString(params: Record<string, QueryValue>): string {
    const search = new URLSearchParams()

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            continue
        }

        search.set(key, String(value))
    }

    const query = search.toString()

    return query === '' ? '' : `?${query}`
}

export function getHome(): Promise<HomeResponse> {
    return apiFetch<HomeResponse>('/home')
}

export function getProductFilters(): Promise<{ data: CatalogFilters }> {
    return apiFetch<{ data: CatalogFilters }>('/products/filters')
}

export function getProducts(query: ProductQuery = {}): Promise<{
    data: ProductSummary[]
    meta: ProductListMeta
}> {
    return apiFetch<{ data: ProductSummary[]; meta: ProductListMeta }>(
        `/products${queryString({ ...query })}`,
    )
}

export function getProductBySlug(slug: string): Promise<{
    data: ProductDetail
    meta: { available_offer_count: number }
}> {
    return apiFetch<{ data: ProductDetail; meta: { available_offer_count: number } }>(
        `/products/${encodeURIComponent(slug)}`,
    )
}

export function searchProducts(query: SearchQuery): Promise<{
    data: ProductSummary[]
    meta: SearchMeta
}> {
    return apiFetch<{ data: ProductSummary[]; meta: SearchMeta }>(
        `/search${queryString({ ...query })}`,
    )
}

export interface AuthPayload {
    data: {
        user: {
            id: number
            name: string
            email: string
            email_verified_at: string | null
            created_at: string
            updated_at: string
        }
        token: string
        token_type: string
    }
}

export function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
}): Promise<AuthPayload> {
    return apiFetch<AuthPayload>('/auth/register', {
        method: 'POST',
        body: { ...payload, cart_token: getCartToken() },
    })
}

export function login(payload: { email: string; password: string }): Promise<AuthPayload> {
    return apiFetch<AuthPayload>('/auth/login', {
        method: 'POST',
        body: { ...payload, cart_token: getCartToken() },
    })
}

export function logout(): Promise<{ data: { message: string } }> {
    return apiFetch<{ data: { message: string } }>('/auth/logout', { method: 'POST' })
}

export function getMe(): Promise<{ data: AuthPayload['data']['user'] }> {
    return apiFetch<{ data: AuthPayload['data']['user'] }>('/auth/me')
}

export async function getCart(): Promise<CartResponse> {
    return rememberCartToken(await apiFetch<CartResponse>('/cart', { headers: cartHeaders() }))
}

export async function addCartItem(payload: {
    product_id: number
    offer_id: number
}): Promise<CartResponse> {
    return rememberCartToken(
        await apiFetch<CartResponse>('/cart/items', {
            method: 'POST',
            body: payload,
            headers: cartHeaders(),
        }),
    )
}

export async function removeCartItem(offerId: number): Promise<CartResponse> {
    return rememberCartToken(
        await apiFetch<CartResponse>(`/cart/items/${offerId}`, {
            method: 'DELETE',
            headers: cartHeaders(),
        }),
    )
}

export function checkout(cardNumber: string): Promise<{ data: Order; meta: CheckoutMeta }> {
    return apiFetch<{ data: Order; meta: CheckoutMeta }>('/checkout', {
        method: 'POST',
        body: { card_number: cardNumber },
        headers: cartHeaders(),
    })
}

export function getOrders(
    query: { page?: number; per_page?: number } = {},
): Promise<{ data: Order[]; meta: { pagination: PaginationMeta } }> {
    return apiFetch<{ data: Order[]; meta: { pagination: PaginationMeta } }>(
        `/orders${queryString({ ...query })}`,
    )
}

export function getOrder(id: number): Promise<{ data: Order }> {
    return apiFetch<{ data: Order }>(`/orders/${id}`)
}
