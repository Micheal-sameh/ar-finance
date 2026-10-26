import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react'

declare global {
    interface Window {
        turnstile?: {
            render: (container: HTMLElement, options: Record<string, unknown>) => string
            reset: (widgetId?: string) => void
            remove: (widgetId?: string) => void
        }
        onloadTurnstileCallback?: () => void
    }
}

const SCRIPT_ID = 'cf-turnstile-script'
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit'

let scriptLoadPromise: Promise<void> | null = null

// The Turnstile script's onload callback must be a single global function,
// so the promise (and the script tag itself) are shared across every widget
// instance on the page rather than requested per-component.
function loadTurnstileScript(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve()
    }

    if (!scriptLoadPromise) {
        scriptLoadPromise = new Promise((resolve) => {
            window.onloadTurnstileCallback = () => resolve()

            if (!document.getElementById(SCRIPT_ID)) {
                const script = document.createElement('script')
                script.id = SCRIPT_ID
                script.src = SCRIPT_SRC
                script.async = true
                script.defer = true
                document.head.appendChild(script)
            }
        })
    }

    return scriptLoadPromise
}

interface TurnstileProps {
    siteKey: string
    onVerify: (token: string) => void
    onExpire?: () => void
    className?: string
}

export interface TurnstileHandle {
    // A verified token is single-use: the server consumes it on the first
    // siteverify call (any submit attempt, including one that then fails
    // for other reasons like wrong credentials), so the widget must issue
    // a fresh token before it can be submitted again.
    reset: () => void
}

const Turnstile = forwardRef<TurnstileHandle, TurnstileProps>(function Turnstile(
    { siteKey, onVerify, onExpire, className },
    ref,
) {
    const containerRef = useRef<HTMLDivElement>(null)
    const widgetIdRef = useRef<string | undefined>(undefined)
    const onVerifyRef = useRef(onVerify)
    const onExpireRef = useRef(onExpire)

    onVerifyRef.current = onVerify
    onExpireRef.current = onExpire

    useImperativeHandle(ref, () => ({
        reset: () => {
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.reset(widgetIdRef.current)
            }
        },
    }), [])

    useEffect(() => {
        let cancelled = false

        loadTurnstileScript().then(() => {
            if (cancelled || !containerRef.current || !window.turnstile) {
                return
            }

            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: siteKey,
                callback: (token: string) => onVerifyRef.current(token),
                'expired-callback': () => onExpireRef.current?.(),
            })
        })

        return () => {
            cancelled = true
            if (widgetIdRef.current && window.turnstile) {
                window.turnstile.remove(widgetIdRef.current)
            }
        }
    }, [siteKey])

    return <div ref={containerRef} className={className} />
})

export default Turnstile
