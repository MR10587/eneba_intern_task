import { Navigate, useLocation } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../../context/authContext'
import Spinner from '../ui/Spinner'

export default function RequireAuth({ children }: { children: ReactNode }) {
    const { user, loading } = useAuth()
    const location = useLocation()

    if (loading) {
        return (
            <div className="grid min-h-[60vh] place-items-center">
                <Spinner label="Checking session" />
            </div>
        )
    }

    if (!user) {
        return (
            <Navigate
                to="/login"
                replace
                state={{ from: `${location.pathname}${location.search}` }}
            />
        )
    }

    return <>{children}</>
}
