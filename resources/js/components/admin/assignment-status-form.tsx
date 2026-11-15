import { useForm } from '@inertiajs/react';
import TeacherAssignmentController from '@/actions/App/Http/Controllers/Admin/TeacherAssignmentController';
import { ErrorSummary } from '@/components/forms/error-summary';
import { SubmitButton } from '@/components/forms/submit-button';
import { Button } from '@/components/ui/button';

/** Revoke (DELETE, confirmed) or reactivate (PATCH revoked=false). A rejection stays visible as errors. */
export function AssignmentStatusForm({
    mode,
    assignmentId,
    onDone,
    onCancel,
}: {
    mode: 'revoke' | 'reactivate';
    assignmentId: string;
    onDone: () => void;
    onCancel: () => void;
}) {
    const form = useForm({ confirmed: true, revoked: false });
    const revoke = mode === 'revoke';
    const target = { assignment: assignmentId };

    return (
        <form
            noValidate
            aria-busy={form.processing}
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (form.processing) return;
                const options = { preserveScroll: true, onSuccess: onDone };
                if (revoke) {
                    form.delete(
                        TeacherAssignmentController.destroy.url(target),
                        options,
                    );
                } else {
                    form.patch(
                        TeacherAssignmentController.update.url(target),
                        options,
                    );
                }
            }}
        >
            <ErrorSummary
                errors={form.errors}
                fields={{}}
                title={
                    revoke
                        ? 'Nie udało się cofnąć przydziału'
                        : 'Nie udało się przywrócić przydziału'
                }
            />
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
                    variant={revoke ? 'destructive' : 'primary'}
                    className="w-full md:w-auto"
                >
                    {revoke ? 'Cofnij przydział' : 'Przywróć przydział'}
                </SubmitButton>
            </div>
        </form>
    );
}
