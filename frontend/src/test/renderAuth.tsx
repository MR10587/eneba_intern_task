import { render } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import { AuthContext } from '../context/authContext'
import { ToastProvider } from '../context/ToastProvider'
import type { AuthContextValue } from '../context/authContext'
import { authUser } from './fixtures'

interface RenderAuthOptions {
    route?: string
    user?: AuthContextValue['user']
    overrides?: Partial<AuthContextValue>
}

export function renderWithAuth(ui: ReactNode, options: RenderAuthOptions = {}) {
    const user = options.user === undefined ? authUser() : options.user
    const value: AuthContextValue = {
        user,
        token: user ? 'test-token' : null,
        loading: false,
        login: vi.fn(async () => {}),
        register: vi.fn(async () => {}),
        logout: vi.fn(async () => {}),
        ...options.overrides,
    }

    return render(
        <AuthContext.Provider value={value}>
            <ToastProvider>
                <MemoryRouter initialEntries={[options.route ?? '/']}>{ui}</MemoryRouter>
            </ToastProvider>
        </AuthContext.Provider>,
    )
}
