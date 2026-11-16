import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { ComponentProps } from 'react';

/** Reuses each list's GET submission, including its domain filters and page reset. */
export function SearchFilterForm(props: ComponentProps<'form'>) {
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const composingRef = useRef(false);
    function cancel() {
        if (timerRef.current !== null) clearTimeout(timerRef.current);
        timerRef.current = null;
    }
    useEffect(() => {
        // A sort, pagination, reset or Back navigation invalidates a queued query.
        const remove = router.on?.('before', cancel);
        window.addEventListener('popstate', cancel);
        return () => {
            cancel();
            remove?.();
            window.removeEventListener('popstate', cancel);
        };
    }, []);
    function schedule(form: HTMLFormElement, target: EventTarget) {
        cancel();
        if (
            composingRef.current ||
            !(target instanceof HTMLInputElement) ||
            target.type !== 'search'
        )
            return;
        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            form.requestSubmit();
        }, 350);
    }
    return (
        <form
            {...props}
            onChange={(event) => {
                props.onChange?.(event);
                schedule(event.currentTarget, event.target);
            }}
            onCompositionStart={() => {
                composingRef.current = true;
                cancel();
            }}
            onCompositionEnd={(event) => {
                composingRef.current = false;
                schedule(event.currentTarget, event.target);
            }}
            onSubmit={(event) => {
                cancel();
                props.onSubmit?.(event);
            }}
        />
    );
}
