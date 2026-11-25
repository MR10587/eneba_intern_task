import { useCallback, useMemo, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import ToastViewport from '../components/ui/ToastViewport'
import { ToastContext } from './toastContext'
import type { ShowToastOptions, ToastContextValue, ToastMessage } from './toastContext'

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastMessage[]>([])
    const nextId = useRef(1)

    const showToast = useCallback((message: string, options: ShowToastOptions = {}) => {
        const id = nextId.current++

        setToasts((current) => [
            ...current,
            {
                id,
                message,
                tone: options.tone ?? 'success',
                action: options.action,
            },
        ])
    }, [])

    const dismissToast = useCallback((id: number) => {
        setToasts((current) => current.filter((toast) => toast.id !== id))
    }, [])

    const value = useMemo<ToastContextValue>(
        () => ({ showToast, dismissToast }),
        [dismissToast, showToast],
    )

    return (
        <ToastContext.Provider value={value}>
            {children}
            <ToastViewport toasts={toasts} onDismiss={dismissToast} />
        </ToastContext.Provider>
    )
}
