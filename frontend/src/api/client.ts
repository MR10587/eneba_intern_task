export const API_BASE = '/api'

export interface ApiErrorPayload {
    message: string
    errors?: Record<string, string[]>
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
    const headers = new Headers(init?.headers)
    if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json')
    }

    const response = await fetch(`${API_BASE}${path}`, { ...init, headers })

    if (!response.ok) {
        let payload: ApiErrorPayload | undefined
        try {
            payload = (await response.json()) as ApiErrorPayload
        } catch {
            // Non-JSON error response; fall back to the HTTP status.
        }
        throw new Error(payload?.message ?? `Request failed (${response.status})`)
    }

    return (await response.json()) as T
}
