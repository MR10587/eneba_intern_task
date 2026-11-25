import { useCallback, useEffect, useRef, useState } from 'react'
import type { DependencyList } from 'react'

interface AsyncState<T> {
    data: T | null
    error: Error | null
    loading: boolean
}

interface TrackedState<T> extends AsyncState<T> {
    key: string
}

export interface AsyncResult<T> extends AsyncState<T> {
    reload: () => void
}

/**
 * Runs an API-backed factory and keeps loading/error/data state consistent.
 *
 * WHY: every page needs the same three states. The request key is derived from
 * `deps`, so a changed filter immediately exposes a fresh loading state while
 * an in-flight response for the previous key is ignored.
 */
export function useAsync<T>(factory: () => Promise<T>, deps: DependencyList): AsyncResult<T> {
    const [nonce, setNonce] = useState(0)
    const key = JSON.stringify([...deps, nonce])
    const [state, setState] = useState<TrackedState<T>>(() => ({
        key,
        data: null,
        error: null,
        loading: true,
    }))

    const reload = useCallback(() => setNonce((current) => current + 1), [])

    // WHY: keep the latest closure without making a new function reference a
    // fetch trigger. `key` below is the only real reason to refetch.
    const factoryRef = useRef(factory)

    useEffect(() => {
        factoryRef.current = factory
    })

    useEffect(() => {
        let active = true

        factoryRef
            .current()
            .then((data) => {
                if (active) {
                    setState({ key, data, error: null, loading: false })
                }
            })
            .catch((error: unknown) => {
                if (active) {
                    setState({
                        key,
                        data: null,
                        error: error instanceof Error ? error : new Error('Unexpected error'),
                        loading: false,
                    })
                }
            })

        return () => {
            active = false
        }
        // WHY: `factory` is a fresh closure each render; `key` is the real trigger.
    }, [key])

    const visible: AsyncState<T> =
        state.key === key ? state : { data: null, error: null, loading: true }

    return { ...visible, reload }
}
