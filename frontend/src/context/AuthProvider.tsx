import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { getAuthToken, setAuthToken } from '../api/client'
import {
    getMe,
    login as loginRequest,
    logout as logoutRequest,
    register as registerRequest,
} from '../api/endpoints'
import type { AuthUser } from '../types/api'
import { clearCartToken } from '../utils/cartToken'
import { AuthContext } from './authContext'
import type { RegisterValues } from './authContext'

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<AuthUser | null>(null)
    const [token, setToken] = useState<string | null>(() => getAuthToken())
    const [loading, setLoading] = useState(() => Boolean(getAuthToken()))

    useEffect(() => {
        let active = true

        if (!getAuthToken()) {
            return () => {
                active = false
            }
        }

        getMe()
            .then(({ data }) => {
                if (active) {
                    setUser(data)
                }
            })
            .catch(() => {
                if (active) {
                    setAuthToken(null)
                    setToken(null)
                    setUser(null)
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false)
                }
            })

        return () => {
            active = false
        }
    }, [])

    const login = useCallback(async (email: string, password: string) => {
        const { data } = await loginRequest({ email, password })

        setAuthToken(data.token)
        setToken(data.token)
        setUser(data.user)
        // The guest cart was merged during sign-in; do not reuse its token.
        clearCartToken()
    }, [])

    const register = useCallback(async (values: RegisterValues) => {
        const { data } = await registerRequest(values)

        setAuthToken(data.token)
        setToken(data.token)
        setUser(data.user)
        clearCartToken()
    }, [])

    const logout = useCallback(async () => {
        try {
            await logoutRequest()
        } finally {
            setAuthToken(null)
            setToken(null)
            setUser(null)
            clearCartToken()
        }
    }, [])

    const value = useMemo(
        () => ({ user, token, loading, login, register, logout }),
        [loading, login, logout, register, token, user],
    )

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
