import type { ReactNode } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import RequireAuth from './components/auth/RequireAuth'
import StoreShell from './components/layout/StoreShell'
import { AuthProvider } from './context/AuthProvider'
import { ToastProvider } from './context/ToastProvider'
import CartPage from './pages/CartPage'
import CheckoutPage from './pages/CheckoutPage'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import MarketplacePage from './pages/MarketplacePage'
import NotFoundPage from './pages/NotFoundPage'
import OrderDetailPage from './pages/OrderDetailPage'
import OrderHistoryPage from './pages/OrderHistoryPage'
import ProductDetailPage from './pages/ProductDetailPage'
import RegisterPage from './pages/RegisterPage'
import SearchPage from './pages/SearchPage'
import StorePage from './pages/StorePage'

function shell(node: ReactNode, guarded = false) {
    const content = <StoreShell>{node}</StoreShell>

    return guarded ? <RequireAuth>{content}</RequireAuth> : content
}

export default function App() {
    return (
        <BrowserRouter>
            <AuthProvider>
                <ToastProvider>
                    <Routes>
                        <Route path="/" element={<MarketplacePage />} />
                        <Route path="/browse" element={shell(<HomePage />)} />
                        <Route path="/home" element={shell(<HomePage />)} />
                        <Route path="/products" element={shell(<StorePage />)} />
                        <Route path="/products/:slug" element={shell(<ProductDetailPage />)} />
                        <Route path="/search" element={shell(<SearchPage />)} />
                        <Route path="/login" element={shell(<LoginPage />)} />
                        <Route path="/register" element={shell(<RegisterPage />)} />
                        <Route path="/cart" element={shell(<CartPage />)} />
                        <Route path="/checkout" element={shell(<CheckoutPage />, true)} />
                        <Route path="/orders" element={shell(<OrderHistoryPage />, true)} />
                        <Route path="/orders/:id" element={shell(<OrderDetailPage />, true)} />
                        <Route path="*" element={shell(<NotFoundPage />)} />
                    </Routes>
                </ToastProvider>
            </AuthProvider>
        </BrowserRouter>
    )
}
