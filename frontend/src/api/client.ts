import type { ApiErrorPayload } from '../types/api'

/**
 * Local development keeps the Vite `/api` proxy. Deployments set VITE_API_URL
 * to the full API base, including `/api`, because the SPA is served separately.
 */
const configuredApiBase = import.meta.env.VITE_API_URL?.trim()

export const API_BASE = configuredApiBase ? configuredApiBase.replace(/\/+$/, '') : '/api'

const TOKEN_STORAGE_KEY = 'eneba_token'

let authToken: string | null = null

if (typeof window !== 'undefined') {
    try {
        authToken = window.localStorage.getItem(TOKEN_STORAGE_KEY)
    } catch {
        // Storage can be blocked; in-memory auth still works for this session.
        authToken = null
    }
}

export function getAuthToken(): string | null {
    return authToken
}

export function setAuthToken(token: string | null): void {
    authToken = token

    if (typeof window === 'undefined') {
        return
    }

    try {
        if (token) {
            window.localStorage.setItem(TOKEN_STORAGE_KEY, token)
        } else {
            window.localStorage.removeItem(TOKEN_STORAGE_KEY)
        }
    } catch {
        // Ignore storage failures; the in-memory token remains authoritative.
    }
}

export class ApiError extends Error {
    readonly status: number
    readonly code: string
    readonly errors?: Record<string, string[]>

    constructor(status: number, payload: ApiErrorPayload) {
        super(payload.message ?? `Request failed (${status})`)
        this.name = 'ApiError'
        this.status = status
        this.code = payload.code ?? 'INTERNAL_ERROR'
        this.errors = payload.errors
    }
}

export interface ApiRequestOptions extends Omit<RequestInit, 'body'> {
    body?: unknown
    token?: string | null
}

function isErrorPayload(value: unknown): value is ApiErrorPayload {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export async function apiFetch<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
    const { body, token, headers: incomingHeaders, ...rest } = options
    const headers = new Headers(incomingHeaders)

    if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json')
    }

    const bearer = token === undefined ? authToken : token
    if (bearer) {
        headers.set('Authorization', `Bearer ${bearer}`)
    }

    const response = await fetch(`${API_BASE}${path}`, {
        ...rest,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    })

    if (!response.ok) {
        let payload: unknown

        try {
            payload = await response.json()
        } catch {
            // Non-JSON error response; fall back to the HTTP status.
        }

        throw new ApiError(response.status, isErrorPayload(payload) ? payload : {})
    }

    return (await response.json()) as T
}
