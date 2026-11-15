import { Search } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

// FormField owns the visible label, hint, error and their ARIA relationships.
function Input({ className, type, ...props }: ComponentProps<'input'>) {
    const field = (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-12 w-full min-w-0 rounded-md border border-border-strong bg-surface px-3 text-body text-text-primary shadow-none outline-none selection:bg-primary selection:text-text-on-primary placeholder:text-text-secondary placeholder:opacity-100 md:h-10',
                'focus-visible:border-focus focus-visible:shadow-focus focus-visible:outline-2 focus-visible:outline-focus',
                'disabled:cursor-not-allowed disabled:bg-surface-secondary disabled:text-text-disabled disabled:opacity-100 aria-invalid:border-[1.5px] aria-invalid:border-error',
                type === 'search' && 'pl-[38px]',
                className,
            )}
            {...props}
        />
    );
    if (type !== 'search') return field;
    return (
        <div className="relative w-full min-w-0">
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-[18px] -translate-y-1/2 text-text-secondary"
            />
            {field}
        </div>
    );
}
export { Input };
