import { createContext, useContext } from 'react'

export interface ToastAction {
    label: string
    onClick: () => void
}

export interface ToastMessage {
    id: number
    message: string
    tone: 'success' | 'error'
    action?: ToastAction
}

export interface ShowToastOptions {
    tone?: ToastMessage['tone']
    action?: ToastAction
}

export interface ToastContextValue {
    showToast: (message: string, options?: ShowToastOptions) => void
    dismissToast: (id: number) => void
}

export const ToastContext = createContext<ToastContextValue | null>(null)

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext)

    if (!context) {
        throw new Error('useToast must be used inside ToastProvider')
    }

    return context
}
