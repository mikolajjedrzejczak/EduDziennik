import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

import type { GradeValue } from '@/types/grades';

type GradeChipProps = {
    value: GradeValue;
    size?: 'S' | 'M' | 'L';
    deleted?: boolean;
    differsFromAssessment?: boolean;
    className?: string;
} & (
    | { href: string; onClick?: never }
    | {
          href?: never;
          onClick: ComponentProps<'button'>['onClick'];
          disabled?: boolean;
      }
    | { href?: never; onClick?: never }
);

const colors: Record<GradeValue, string> = {
    1: 'border border-grade-1-border bg-grade-1-bg text-grade-1-fg',
    2: 'border border-grade-2-border bg-grade-2-bg text-grade-2-fg',
    3: 'border border-grade-3-border bg-grade-3-bg text-grade-3-fg',
    4: 'border border-grade-4-border bg-grade-4-bg text-grade-4-fg',
    5: 'border border-grade-5-border bg-grade-5-bg text-grade-5-fg',
    6: 'bg-grade-6-bg text-grade-6-fg',
};
const sizes = {
    S: 'size-6 rounded-sm text-caption font-medium',
    M: 'size-8 rounded-md text-grade font-bold',
    L: 'size-14 rounded-xl text-numeric-lg font-semibold tracking-[-0.4px]',
};

export function GradeChip(props: GradeChipProps) {
    const {
        value,
        size = 'M',
        deleted = false,
        differsFromAssessment = false,
        className,
    } = props;
    const label = `Ocena ${value}${deleted ? ', usunięta' : ''}${differsFromAssessment ? ', indywidualne parametry oceny' : ''}`;
    const interactive = Boolean(props.href || props.onClick);
    const graphic = (
        <span
            aria-hidden="true"
            data-slot="grade-chip-value"
            className={cn(
                'inline-flex shrink-0 items-center justify-center tabular-nums',
                colors[value],
                sizes[size],
                deleted && 'line-through decoration-2',
            )}
        >
            {value}
        </span>
    );
    const classes = cn(
        'inline-flex shrink-0 items-center justify-center',
        interactive &&
            'min-h-11 min-w-11 rounded-md outline-none focus-visible:shadow-focus focus-visible:outline-2 focus-visible:outline-focus disabled:pointer-events-none',
        className,
    );
    const content = (
        <>
            {graphic}
            {differsFromAssessment && (
                <span aria-hidden="true" className="ml-1 text-text-secondary">
                    *
                </span>
            )}
        </>
    );
    if (props.href)
        return (
            <Link href={props.href} aria-label={label} className={classes}>
                {content}
            </Link>
        );
    if (props.onClick)
        return (
            <button
                type="button"
                onClick={props.onClick}
                disabled={'disabled' in props && props.disabled}
                aria-label={label}
                className={classes}
            >
                {content}
            </button>
        );
    return (
        <span className={classes}>
            {content}
            <span className="sr-only">{label}</span>
        </span>
    );
}
