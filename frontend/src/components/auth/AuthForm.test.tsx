import { fireEvent, screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { renderWithAuth } from '../../test/renderAuth'
import LoginPage from '../../pages/LoginPage'
import AuthForm from './AuthForm'

describe('AuthForm', () => {
    it('submits login credentials and returns to the storefront by default', async () => {
        const login = vi.fn(async () => {})

        renderWithAuth(
            <Routes>
                <Route path="/login" element={<AuthForm mode="login" />} />
                <Route path="/" element={<p>Home page</p>} />
            </Routes>,
            { route: '/login', user: null, overrides: { login } },
        )

        fireEvent.change(screen.getByLabelText('Email'), {
            target: { value: 'ada@example.com' },
        })
        fireEvent.change(screen.getByLabelText('Password'), {
            target: { value: 'password' },
        })
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }))

        await waitFor(() => {
            expect(login).toHaveBeenCalledWith('ada@example.com', 'password')
        })
        expect(await screen.findByText('Home page')).toBeInTheDocument()
    })

    it('renders the seeded demo credentials on the login page', () => {
        renderWithAuth(<LoginPage />, { user: null })

        expect(screen.getByText(/test@example.com \/ password/)).toBeInTheDocument()
    })
})
