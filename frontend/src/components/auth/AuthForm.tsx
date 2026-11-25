import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/authContext'
import { apiErrorMessages } from '../../utils/errors'
import Button from '../ui/Button'

interface AuthFormProps {
    mode: 'login' | 'register'
}

export default function AuthForm({ mode }: AuthFormProps) {
    const { login, register } = useAuth()
    const navigate = useNavigate()
    const location = useLocation()
    const [values, setValues] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    })
    const [errors, setErrors] = useState<string[]>([])
    const [submitting, setSubmitting] = useState(false)

    const isLogin = mode === 'login'
    const redirectTo =
        (location.state as { from?: string } | null)?.from ?? (isLogin ? '/' : '/browse')

    function update(key: keyof typeof values, value: string): void {
        setValues((current) => ({ ...current, [key]: value }))
    }

    async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault()
        setErrors([])
        setSubmitting(true)

        try {
            if (isLogin) {
                await login(values.email, values.password)
            } else {
                await register(values)
            }

            navigate(redirectTo, { replace: true })
        } catch (error: unknown) {
            setErrors(apiErrorMessages(error))
        } finally {
            setSubmitting(false)
        }
    }

    return (
        <div className="mx-auto max-w-md rounded-2xl border border-white/10 bg-royal p-6">
            <p className="text-xs font-black tracking-widest text-sun uppercase">
                {isLogin ? 'Welcome back' : 'Create account'}
            </p>
            <h1 className="mt-1 text-2xl font-black text-white">
                {isLogin ? 'Sign in to continue' : 'Join Eneba'}
            </h1>
            <p className="mt-2 text-sm text-lavender">
                {isLogin
                    ? 'Use your email and password to check out and view your orders.'
                    : 'Register to buy keys and track your orders.'}
            </p>

            <form className="mt-6 space-y-4" onSubmit={(event) => void handleSubmit(event)}>
                {!isLogin && (
                    <label className="block">
                        <span className="mb-1 block text-xs font-bold text-lavender">Name</span>
                        <input
                            required
                            value={values.name}
                            onChange={(event) => update('name', event.target.value)}
                            className="w-full rounded-xl border border-white/10 bg-midnight px-3 py-2.5 text-sm text-white focus:border-sun focus:outline-none"
                        />
                    </label>
                )}

                <label className="block">
                    <span className="mb-1 block text-xs font-bold text-lavender">Email</span>
                    <input
                        required
                        type="email"
                        value={values.email}
                        onChange={(event) => update('email', event.target.value)}
                        className="w-full rounded-xl border border-white/10 bg-midnight px-3 py-2.5 text-sm text-white focus:border-sun focus:outline-none"
                    />
                </label>

                <label className="block">
                    <span className="mb-1 block text-xs font-bold text-lavender">Password</span>
                    <input
                        required
                        type="password"
                        minLength={isLogin ? undefined : 8}
                        value={values.password}
                        onChange={(event) => update('password', event.target.value)}
                        className="w-full rounded-xl border border-white/10 bg-midnight px-3 py-2.5 text-sm text-white focus:border-sun focus:outline-none"
                    />
                </label>

                {!isLogin && (
                    <label className="block">
                        <span className="mb-1 block text-xs font-bold text-lavender">
                            Confirm password
                        </span>
                        <input
                            required
                            type="password"
                            minLength={8}
                            value={values.password_confirmation}
                            onChange={(event) =>
                                update('password_confirmation', event.target.value)
                            }
                            className="w-full rounded-xl border border-white/10 bg-midnight px-3 py-2.5 text-sm text-white focus:border-sun focus:outline-none"
                        />
                    </label>
                )}

                {errors.length > 0 && (
                    <div
                        role="alert"
                        className="space-y-1 rounded-xl bg-promo/10 p-3 text-sm text-promo"
                    >
                        {errors.map((message) => (
                            <p key={message}>{message}</p>
                        ))}
                    </div>
                )}

                <Button type="submit" disabled={submitting} className="w-full">
                    {submitting ? 'Please wait…' : isLogin ? 'Sign in' : 'Create account'}
                </Button>
            </form>

            <p className="mt-5 text-center text-sm text-lavender">
                {isLogin ? 'Need an account?' : 'Already have an account?'}{' '}
                <Link
                    className="font-bold text-sun hover:underline"
                    to={isLogin ? '/register' : '/login'}
                    state={(location.state as { from?: string } | null) ?? undefined}
                >
                    {isLogin ? 'Register' : 'Sign in'}
                </Link>
            </p>

            {isLogin && (
                <p className="mt-4 rounded-xl border border-white/10 bg-midnight p-3 text-center text-xs text-lavender/80">
                    Seeded demo: test@example.com / password
                </p>
            )}
        </div>
    )
}
