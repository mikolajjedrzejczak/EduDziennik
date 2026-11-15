import { CircleAlert } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'flex items-start gap-1.5 font-form text-xs leading-4 wrap-anywhere text-form-error',
                className,
            )}
        >
            <CircleAlert
                aria-hidden="true"
                className="mt-px size-3.5 shrink-0"
            />
            <span>{message}</span>
        </p>
    ) : null;
}
