import { beforeEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse } from '../test/fixtures'
import { ApiError, apiFetch, getAuthToken, setAuthToken } from './client'

describe('apiFetch', () => {
    beforeEach(() => {
        setAuthToken(null)
    })

    it('returns JSON, prefixes /api, and sends an explicit bearer token', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(jsonResponse({ data: { ok: true }, meta: { count: 1 } }))
        vi.stubGlobal('fetch', fetchMock)

        await expect(apiFetch('/home', { token: 'test-token' })).resolves.toEqual({
            data: { ok: true },
            meta: { count: 1 },
        })

        expect(fetchMock).toHaveBeenCalledTimes(1)
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit]
        expect(url).toBe('/api/home')
        const headers = init.headers as Headers
        expect(headers.get('Authorization')).toBe('Bearer test-token')
        expect(headers.get('Content-Type')).toBe('application/json')
    })

    it('persists the token and sends it without an explicit override', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(jsonResponse({ data: { items: [], total_cents: 0 } }))
        vi.stubGlobal('fetch', fetchMock)

        setAuthToken('saved-token')

        expect(getAuthToken()).toBe('saved-token')
        expect(window.localStorage.getItem('eneba_token')).toBe('saved-token')

        await apiFetch('/cart')

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit]
        expect((init.headers as Headers).get('Authorization')).toBe('Bearer saved-token')

        setAuthToken(null)

        expect(getAuthToken()).toBeNull()
        expect(window.localStorage.getItem('eneba_token')).toBeNull()
    })

    it('throws a coded ApiError for a JSON error response', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                jsonResponse(
                    {
                        message: 'Product not found.',
                        code: 'PRODUCT_NOT_FOUND',
                    },
                    404,
                ),
            ),
        )

        const error = await apiFetch('/products/missing').catch((caught: unknown) => caught)

        expect(error).toBeInstanceOf(ApiError)
        expect((error as ApiError).status).toBe(404)
        expect((error as ApiError).code).toBe('PRODUCT_NOT_FOUND')
        expect((error as ApiError).message).toBe('Product not found.')
    })

    it('falls back to the HTTP status for non-JSON errors', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: false,
                status: 500,
                json: async () => {
                    throw new Error('not json')
                },
            }),
        )

        const error = await apiFetch('/broken').catch((caught: unknown) => caught)

        expect(error).toBeInstanceOf(ApiError)
        expect((error as ApiError).code).toBe('INTERNAL_ERROR')
    })
})
