import Button from './Button'

interface ErrorStateProps {
    title?: string
    message: string
    onRetry?: () => void
}

export default function ErrorState({
    title = 'Something went wrong',
    message,
    onRetry,
}: ErrorStateProps) {
    return (
        <div className="state-panel state-panel--error" role="alert">
            <h2>{title}</h2>
            <p>{message}</p>
            {onRetry && <Button onClick={onRetry}>Try again</Button>}
        </div>
    )
}
