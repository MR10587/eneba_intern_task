import { AlertCircle, CheckCircle2, X } from 'lucide-react'
import { useEffect } from 'react'
import type { ToastMessage } from '../../context/toastContext'

interface ToastViewportProps {
    toasts: ToastMessage[]
    onDismiss: (id: number) => void
}

const TOAST_DURATION_MS = 4000

function ToastItem({ toast, onDismiss }: { toast: ToastMessage; onDismiss: (id: number) => void }) {
    useEffect(() => {
        const timer = window.setTimeout(() => onDismiss(toast.id), TOAST_DURATION_MS)

        return () => window.clearTimeout(timer)
    }, [onDismiss, toast.id])

    const isSuccess = toast.tone === 'success'
    const Icon = isSuccess ? CheckCircle2 : AlertCircle

    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border border-white/10 bg-royal p-4 shadow-2xl"
        >
            <Icon
                className={`mt-0.5 h-5 w-5 shrink-0 ${isSuccess ? 'text-trust' : 'text-promo'}`}
                aria-hidden="true"
            />

            <div className="min-w-0 flex-1">
                <p className="text-sm font-bold text-white">{toast.message}</p>
                {toast.action && (
                    <button
                        type="button"
                        onClick={() => {
                            toast.action?.onClick()
                            onDismiss(toast.id)
                        }}
                        className="mt-2 text-xs font-black text-sun hover:underline"
                    >
                        {toast.action.label}
                    </button>
                )}
            </div>

            <button
                type="button"
                onClick={() => onDismiss(toast.id)}
                aria-label="Dismiss notification"
                className="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-lavender hover:bg-white/10 hover:text-white"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    )
}

export default function ToastViewport({ toasts, onDismiss }: ToastViewportProps) {
    return (
        <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex flex-col gap-2">
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} onDismiss={onDismiss} />
            ))}
        </div>
    )
}
