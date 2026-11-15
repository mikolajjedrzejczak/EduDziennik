import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { useOnlineStatus } from '@/hooks/use-online-status';
import { cn } from '@/lib/utils';

export function SubmitButton({
    processing,
    children,
    disabled,
    className,
    ...props
}: Omit<ComponentProps<typeof Button>, 'type' | 'asChild' | 'loading'> & {
    processing: boolean;
}) {
    const online = useOnlineStatus();

    return (
        <Button
            {...props}
            type="submit"
            disabled={disabled || !online}
            loading={processing}
            aria-busy={processing}
            className={cn('h-12 font-form md:h-10', className)}
        >
            {children}
        </Button>
    );
}
