import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { renderWithAuth } from '../../test/renderAuth'
import RequireAuth from './RequireAuth'

describe('RequireAuth', () => {
    it('redirects signed-out visitors to login', () => {
        renderWithAuth(
            <Routes>
                <Route path="/login" element={<p>Login page</p>} />
                <Route
                    path="/checkout"
                    element={
                        <RequireAuth>
                            <p>Checkout page</p>
                        </RequireAuth>
                    }
                />
            </Routes>,
            { route: '/checkout', user: null },
        )

        expect(screen.getByText('Login page')).toBeInTheDocument()
    })

    it('renders guarded content for a signed-in user', () => {
        renderWithAuth(
            <RequireAuth>
                <p>Cart page</p>
            </RequireAuth>,
        )

        expect(screen.getByText('Cart page')).toBeInTheDocument()
    })
})
