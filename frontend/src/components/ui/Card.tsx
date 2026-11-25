import type { HTMLAttributes, ReactNode } from 'react'

interface CardProps extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode
}

export default function Card({ className = '', children, ...props }: CardProps) {
    return (
        <div className={`card ${className}`.trim()} {...props}>
            {children}
        </div>
    )
}
