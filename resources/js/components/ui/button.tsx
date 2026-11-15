import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const secondary =
    'border border-border-strong bg-surface text-text-primary hover:bg-surface-secondary';
const primary = 'bg-primary text-text-on-primary hover:bg-primary-hover';
const buttonVariants = cva(
    'inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-label font-medium whitespace-nowrap transition-colors outline-none focus-visible:shadow-focus focus-visible:outline-2 focus-visible:outline-focus disabled:pointer-events-none disabled:border-transparent disabled:bg-surface-secondary disabled:text-text-disabled disabled:opacity-100 aria-busy:pointer-events-none motion-reduce:transition-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4',
    {
        variants: {
            variant: {
                default: primary,
                primary,
                secondary,
                outline: secondary,
                ghost: 'bg-transparent text-primary hover:bg-primary-subtle disabled:bg-transparent',
                destructive: 'bg-error text-text-on-primary hover:bg-error',
                link: 'text-primary underline-offset-4 hover:underline disabled:bg-transparent',
            },
            size: {
                default: 'h-12 px-4 md:h-10',
                sm: 'h-11 px-3 md:h-8',
                lg: 'h-12 px-6',
                icon: 'size-12 md:size-10',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    },
);

type ButtonProps = ComponentProps<'button'> &
    VariantProps<typeof buttonVariants> &
    (
        | { asChild: true; loading?: never }
        | { asChild?: false; loading?: boolean }
    );

function Button({
    className,
    variant,
    size,
    asChild = false,
    loading = false,
    disabled,
    children,
    type,
    ...props
}: ButtonProps) {
    const Comp = asChild ? Slot : 'button';
    const loader =
        variant === 'ghost' || variant === 'link'
            ? 'primary'
            : variant === 'secondary' || variant === 'outline'
              ? 'text'
              : 'on-primary';
    return (
        <Comp
            data-slot="button"
            type={asChild ? undefined : (type ?? 'button')}
            {...props}
            disabled={disabled || loading}
            aria-busy={loading || props['aria-busy']}
            className={cn(
                buttonVariants({ variant, size, className }),
                loading &&
                    'disabled:border-border-strong disabled:bg-surface disabled:text-text-primary',
                loading &&
                    (variant === 'default' ||
                        variant === 'primary' ||
                        !variant) &&
                    'disabled:border-transparent disabled:bg-primary disabled:text-text-on-primary',
                loading &&
                    variant === 'destructive' &&
                    'disabled:bg-error disabled:text-text-on-primary',
                loading &&
                    (variant === 'ghost' || variant === 'link') &&
                    'disabled:bg-transparent disabled:text-primary',
            )}
        >
            {asChild ? (
                children
            ) : (
                <>
                    {loading && (
                        <img
                            src={`/images/ui/loader-${loader}.svg`}
                            alt=""
                            aria-hidden="true"
                            className="size-4 animate-spin motion-reduce:animate-none"
                        />
                    )}
                    {children}
                </>
            )}
        </Comp>
    );
}
export { Button, buttonVariants };
