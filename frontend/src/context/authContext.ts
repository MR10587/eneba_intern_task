import { createContext, useContext } from 'react'
import type { AuthUser } from '../types/api'

export interface RegisterValues {
    name: string
    email: string
    password: string
    password_confirmation: string
}

export interface AuthContextValue {
    user: AuthUser | null
    token: string | null
    loading: boolean
    login: (email: string, password: string) => Promise<void>
    register: (values: RegisterValues) => Promise<void>
    logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext)

    if (!context) {
        throw new Error('useAuth must be used inside AuthProvider')
    }

    return context
}
