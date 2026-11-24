import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('SPA scaffold', () => {
    it('renders the placeholder shell', () => {
        render(<App />)
        expect(screen.getByText('Eneba Storefront')).toBeTruthy()
    })
})
