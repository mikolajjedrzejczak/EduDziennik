import { Inbox, SearchX } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type EmptyStateAction = { label: string; onClick: () => void };

export function EmptyState({
    variant = 'no-data',
    title,
    description,
    action,
}: {
    variant?: 'no-data' | 'no-results' | 'no-assignment';
    title: string;
    description: string;
    action?: EmptyStateAction;
}) {
    const Icon = variant === 'no-results' ? SearchX : Inbox;

    return (
        <div
            data-variant={variant}
            className="mx-auto flex max-w-sm flex-col items-center gap-2 px-4 py-10 text-center"
        >
            <span className="mb-2 flex size-12 items-center justify-center rounded-full bg-surface-secondary text-text-secondary">
                <Icon aria-hidden="true" className="size-5" />
            </span>
            <h2 className="text-base font-semibold text-text-primary">
                {title}
            </h2>
            <p className="text-sm text-text-secondary">{description}</p>
            {action && (
                <Button
                    variant="secondary"
                    className="mt-3 w-full md:w-auto"
                    onClick={action.onClick}
                >
                    {action.label}
                </Button>
            )}
        </div>
    );
}
