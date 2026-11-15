import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export function SubmitButton({
    processing,
    children,
    disabled,
    className,
    ...props
}: Omit<ComponentProps<typeof Button>, 'type' | 'asChild'> & {
    processing: boolean;
}) {
    return (
        <Button
            {...props}
            type="submit"
            disabled={disabled || processing}
            aria-busy={processing}
            className={cn('h-12 font-form md:h-10', className)}
        >
            {processing && <Spinner aria-hidden="true" />}
            {children}
        </Button>
    );
}
