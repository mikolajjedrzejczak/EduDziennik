import { useForm } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const fields = {
    name: { id: 'subject-name', label: 'Nazwa przedmiotu' },
};

/** Create/rename form. Validation stays on the server; errors come from useForm. */
export function SubjectForm({
    method,
    url,
    initialName = '',
    submitLabel,
    onDone,
    onCancel,
}: {
    method: 'post' | 'patch';
    url: string;
    initialName?: string;
    submitLabel: string;
    onDone: () => void;
    onCancel: () => void;
}) {
    const form = useForm({ name: initialName });

    return (
        <form
            noValidate
            aria-busy={form.processing}
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (form.processing) return;
                form[method](url, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <ErrorSummary errors={form.errors} fields={fields} />
            <FormField
                {...fields.name}
                error={form.errors.name}
                hint="Do 100 znaków. Nazwa musi być unikalna, także wśród zarchiwizowanych."
            >
                {(attributes) => (
                    <Input
                        {...attributes}
                        name="name"
                        required
                        maxLength={100}
                        autoComplete="off"
                        autoFocus
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                )}
            </FormField>
            <div className="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={onCancel}
                    className="w-full md:w-auto"
                >
                    Anuluj
                </Button>
                <SubmitButton
                    processing={form.processing}
                    className="w-full md:w-auto"
                >
                    {submitLabel}
                </SubmitButton>
            </div>
        </form>
    );
}
