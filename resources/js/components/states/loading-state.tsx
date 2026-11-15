import { Skeleton } from '@/components/ui/skeleton';

export function LoadingState({
    label = 'Ładowanie',
    rows = 5,
}: {
    label?: string;
    rows?: number;
}) {
    return (
        <div
            role="status"
            aria-busy="true"
            aria-label={label}
            className="flex flex-col gap-4"
        >
            {Array.from({ length: rows }, (_, index) => (
                <div key={index} className="flex items-center gap-3">
                    <Skeleton className="size-8 shrink-0 bg-surface-secondary" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-3 w-3/4 bg-surface-secondary" />
                        <Skeleton className="h-2 w-1/2 bg-surface-secondary" />
                    </div>
                </div>
            ))}
        </div>
    );
}
