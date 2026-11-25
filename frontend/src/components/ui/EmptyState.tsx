import type { ReactNode } from 'react'
import Button from './Button'

interface EmptyStateProps {
    title: string
    message: string
    action?: { label: string; onClick: () => void }
    children?: ReactNode
}

export default function EmptyState({ title, message, action, children }: EmptyStateProps) {
    return (
        <div className="state-panel">
            <h2>{title}</h2>
            <p>{message}</p>
            {action && <Button onClick={action.onClick}>{action.label}</Button>}
            {children}
        </div>
    )
}
