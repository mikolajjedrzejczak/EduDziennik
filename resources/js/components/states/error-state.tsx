import { CircleAlert } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export function ErrorState({
    title,
    description,
    onRetry,
    retrying = false,
    correlationId,
    children,
}: {
    title: string;
    description: string;
    /** Provide only for safe, repeatable reads (GET). Never for mutations. */
    onRetry?: () => void;
    retrying?: boolean;
    correlationId?: string;
    children?: ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        ref.current?.focus();
    }, []);

    return (
        <div
            ref={ref}
            role="alert"
            tabIndex={-1}
            className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface px-4 py-8 text-center outline-none focus-visible:outline-2 focus-visible:outline-focus"
        >
            <span className="mb-2 flex size-12 items-center justify-center rounded-full bg-error-bg text-error">
                <CircleAlert aria-hidden="true" className="size-5" />
            </span>
            <h2 className="text-base font-semibold text-text-primary">
                {title}
            </h2>
            <p className="max-w-prose text-sm text-text-secondary">
                {description}
            </p>
            {correlationId && (
                <p className="text-xs text-text-secondary">
                    Identyfikator zgłoszenia:{' '}
                    <span className="font-mono">{correlationId}</span>
                </p>
            )}
            {children}
            {onRetry && (
                <Button
                    variant="secondary"
                    className="mt-3 w-full md:w-auto"
                    loading={retrying}
                    onClick={onRetry}
                >
                    Spróbuj ponownie
                </Button>
            )}
        </div>
    );
}
