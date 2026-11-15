import { useForm } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { SubmitButton } from '@/components/forms/submit-button';
import { Button } from '@/components/ui/button';

/** Archive confirmation. A rejected archive (active assignments) stays visible as errors.form. */
export function ArchiveSubjectForm({
    url,
    activeAssignmentsCount,
    onDone,
    onCancel,
}: {
    url: string;
    activeAssignmentsCount: number;
    onDone: () => void;
    onCancel: () => void;
}) {
    const form = useForm({ confirmed: true });

    return (
        <form
            noValidate
            aria-busy={form.processing}
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (form.processing) return;
                form.delete(url, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <ErrorSummary
                errors={form.errors}
                fields={{}}
                title="Nie udało się zarchiwizować przedmiotu"
            />
            {activeAssignmentsCount > 0 && (
                <p className="text-sm text-text-secondary">
                    Przedmiot ma aktywne przydziały nauczycieli (
                    {activeAssignmentsCount}). Serwer odrzuci archiwizację,
                    dopóki nie zostaną cofnięte.
                </p>
            )}
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
                    variant="destructive"
                    className="w-full md:w-auto"
                >
                    Archiwizuj
                </SubmitButton>
            </div>
        </form>
    );
}
