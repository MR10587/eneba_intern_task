import { useState } from 'react'
import type { FormEvent } from 'react'
import Button from '../ui/Button'

interface PaymentFormProps {
    totalLabel: string
    submitting: boolean
    error: string | null
    onSubmit: (cardNumber: string) => void
}

export default function PaymentForm({ totalLabel, submitting, error, onSubmit }: PaymentFormProps) {
    const [cardNumber, setCardNumber] = useState('')

    function handleSubmit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault()
        onSubmit(cardNumber)
    }

    return (
        <form className="rounded-2xl border border-white/10 bg-royal p-6" onSubmit={handleSubmit}>
            <h2 className="text-lg font-black text-white">Simulated payment</h2>
            <p className="mt-1 text-sm text-lavender">
                Cards ending in <strong className="text-promo">0002</strong> are declined. No real
                payment is processed.
            </p>

            <label className="mt-5 block">
                <span className="mb-1 block text-xs font-bold text-lavender">Card number</span>
                <input
                    required
                    inputMode="numeric"
                    autoComplete="cc-number"
                    value={cardNumber}
                    onChange={(event) => setCardNumber(event.target.value)}
                    placeholder="4242 4242 4242 4242"
                    className="w-full rounded-xl border border-white/10 bg-midnight px-3 py-2.5 text-sm text-white placeholder:text-lavender/60 focus:border-sun focus:outline-none"
                />
            </label>

            {error && (
                <p role="alert" className="mt-3 rounded-xl bg-promo/10 p-3 text-sm text-promo">
                    {error}
                </p>
            )}

            <div className="mt-5 flex items-center justify-between border-t border-white/10 pt-4">
                <span className="text-sm text-lavender">Total</span>
                <span className="text-xl font-black text-white">{totalLabel}</span>
            </div>

            <Button type="submit" disabled={submitting} className="mt-4 w-full">
                {submitting ? 'Processing…' : `Pay ${totalLabel}`}
            </Button>
        </form>
    )
}
