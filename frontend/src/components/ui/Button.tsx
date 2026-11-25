import type { ButtonHTMLAttributes, ReactNode } from 'react'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'ghost'
    children: ReactNode
}

export default function Button({
    variant = 'primary',
    className = '',
    type = 'button',
    children,
    ...props
}: ButtonProps) {
    return (
        <button type={type} className={`btn btn--${variant} ${className}`.trim()} {...props}>
            {children}
        </button>
    )
}
