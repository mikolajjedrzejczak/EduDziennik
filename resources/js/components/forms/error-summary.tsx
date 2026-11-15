import { CircleAlert } from 'lucide-react';
import { useEffect, useId, useRef } from 'react';

export type ErrorSummaryField = { id: string; label: string };

export function ErrorSummary({
    errors,
    fields,
}: {
    errors: Readonly<Record<string, string | undefined>>;
    fields: Readonly<Record<string, ErrorSummaryField>>;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const titleId = useId();
    const entries = Object.entries(errors).filter((entry) => Boolean(entry[1]));

    useEffect(() => {
        if (Object.values(errors).some(Boolean)) ref.current?.focus();
    }, [errors]);

    if (entries.length === 0) return null;

    return (
        <div
            ref={ref}
            role="alert"
            tabIndex={-1}
            aria-labelledby={titleId}
            className="rounded-md border border-form-error bg-form-error-bg p-4 font-form text-form-error outline-offset-4 focus-visible:outline-2 focus-visible:outline-form-focus"
        >
            <h2 id={titleId} className="flex items-center gap-2 font-semibold">
                <CircleAlert aria-hidden="true" className="size-4 shrink-0" />
                Nie udało się zapisać formularza
            </h2>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm wrap-anywhere">
                {entries.map(([name, message]) => {
                    const field = Object.hasOwn(fields, name)
                        ? fields[name]
                        : undefined;
                    return (
                        <li key={name}>
                            {field ? (
                                <a
                                    className="inline-block min-h-11 py-2 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-form-focus md:min-h-0 md:py-0"
                                    href={`#${field.id}`}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        document
                                            .getElementById(field.id)
                                            ?.focus();
                                    }}
                                >
                                    {field.label}: {message}
                                </a>
                            ) : (
                                message
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
