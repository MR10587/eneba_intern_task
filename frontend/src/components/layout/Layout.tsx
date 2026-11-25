import type { ReactNode } from 'react'
import Footer from './Footer'
import Header from './Header'

export default function Layout({ children }: { children: ReactNode }) {
    return (
        <div className="app-shell">
            <Header />
            <main className="page">{children}</main>
            <Footer />
        </div>
    )
}
