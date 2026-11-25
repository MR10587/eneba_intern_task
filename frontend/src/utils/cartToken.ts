const CART_TOKEN_STORAGE_KEY = 'eneba_cart_token'

let cartToken: string | null = null

function fallbackUuid(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16)
        const value = character === 'x' ? random : (random & 0x3) | 0x8

        return value.toString(16)
    })
}

if (typeof window !== 'undefined') {
    try {
        cartToken = window.localStorage.getItem(CART_TOKEN_STORAGE_KEY)
    } catch {
        cartToken = null
    }
}

export function getCartToken(): string | null {
    return cartToken
}

export function ensureCartToken(): string {
    if (cartToken) {
        return cartToken
    }

    const token =
        typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
            ? crypto.randomUUID()
            : fallbackUuid()
    setCartToken(token)

    return token
}

export function setCartToken(token: string | null): void {
    cartToken = token

    if (typeof window === 'undefined') {
        return
    }

    try {
        if (token) {
            window.localStorage.setItem(CART_TOKEN_STORAGE_KEY, token)
        } else {
            window.localStorage.removeItem(CART_TOKEN_STORAGE_KEY)
        }
    } catch {
        // Ignore storage failures; the in-memory token still works this session.
    }
}

export function clearCartToken(): void {
    setCartToken(null)
}
