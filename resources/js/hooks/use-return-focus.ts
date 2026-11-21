import { useRef } from 'react';

/**
 * Dialogs opened without a Radix trigger: remember the opener and return focus
 * to it on close, or to `fallback` when the opener left the DOM (e.g. its row).
 */
export function useReturnFocus() {
    const openerRef = useRef<HTMLElement | null>(null);

    return {
        remember() {
            openerRef.current =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
        },
        restore(fallback?: HTMLElement | null) {
            (openerRef.current?.isConnected
                ? openerRef.current
                : fallback
            )?.focus();
        },
    };
}
