import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type FieldAttributes = {
    id: string;
    'aria-invalid': true | undefined;
    'aria-describedby': string | undefined;
};

export function FormField({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: (attributes: FieldAttributes) => ReactNode;
}) {
    const description = [hint && `${id}-hint`, error && `${id}-error`]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="form-field grid min-w-0 gap-1.5 font-form">
            <Label
                htmlFor={id}
                className="text-[13px] leading-4 text-form-text"
            >
                {label}
            </Label>
            {children({
                id,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': description || undefined,
            })}
            {hint && (
                <p
                    id={`${id}-hint`}
                    className="text-xs leading-4 text-form-muted"
                >
                    {hint}
                </p>
            )}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
