import { ApiError } from '../api/client'

/** Flattens the API's coded error envelope into user-facing messages. */
export function apiErrorMessages(error: unknown): string[] {
    if (!(error instanceof ApiError)) {
        return ['Something went wrong. Please try again.']
    }

    const fieldErrors = Object.values(error.errors ?? {}).flat()

    return fieldErrors.length > 0 ? fieldErrors : [error.message]
}

export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
    return apiErrorMessages(error)[0] ?? fallback
}
