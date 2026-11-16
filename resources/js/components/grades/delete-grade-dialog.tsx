import { router, useForm } from '@inertiajs/react';
import * as Dialog from '@radix-ui/react-dialog';
import { Trash, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { GradeDTO } from '@/types/grades';

export function DeleteGradeDialog({
    grade,
    subject,
    assignmentId,
    url,
}: {
    grade: GradeDTO;
    subject: string;
    assignmentId: string;
    url: string;
}) {
    const [open, setOpen] = useState(false);
    const [uncertain, setUncertain] = useState(false);
    const cancelRef = useRef<HTMLButtonElement>(null);
    const submittingRef = useRef(false);
    const form = useForm({
        assignment_id: assignmentId,
        version: grade.version,
        reason: '',
        confirmed: true,
    });
    function close() {
        if (form.processing || submittingRef.current) return;
        setOpen(false);
        form.reset();
        form.clearErrors();
    }
    return (
        <Dialog.Root
            open={open}
            onOpenChange={(value) => (value ? setOpen(true) : close())}
        >
            <Dialog.Trigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="text-error md:mr-auto"
                >
                    <Trash aria-hidden="true" /> Usuń ocenę
                </Button>
            </Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-nav-bg/45" />
                <Dialog.Content
                    className="fixed inset-x-0 bottom-0 z-50 max-h-[95dvh] overflow-y-auto rounded-t-2xl bg-surface text-text-primary shadow-overlay outline-none md:inset-x-auto md:top-1/2 md:bottom-auto md:left-1/2 md:w-[440px] md:max-w-[calc(100%-2rem)] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-xl"
                    onOpenAutoFocus={(e) => {
                        e.preventDefault();
                        cancelRef.current?.focus();
                    }}
                    onEscapeKeyDown={(e) => {
                        if (form.processing || submittingRef.current)
                            e.preventDefault();
                    }}
                    onInteractOutside={(e) => e.preventDefault()}
                >
                    <div
                        aria-hidden="true"
                        className="mx-auto mt-2 h-1 w-9 rounded bg-border-strong md:hidden"
                    />
                    <form
                        noValidate
                        aria-busy={form.processing}
                        onSubmit={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            if (form.processing || submittingRef.current)
                                return;
                            submittingRef.current = true;
                            form.submit('delete', url, {
                                preserveScroll: true,
                                onNetworkError: () => {
                                    setUncertain(true);
                                    return false;
                                },
                                onFinish: () => {
                                    submittingRef.current = false;
                                },
                            });
                        }}
                    >
                        <div className="space-y-4 px-5 py-5 md:px-6 md:py-6">
                            <div className="flex items-start justify-between gap-2">
                                <Dialog.Title className="text-lg font-semibold">
                                    Usunąć ocenę {grade.value}?
                                </Dialog.Title>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Zamknij potwierdzenie"
                                    className="-mt-2 -mr-2 md:hidden"
                                    disabled={form.processing}
                                    onClick={close}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </div>
                            <Dialog.Description className="text-sm wrap-anywhere text-text-secondary">
                                {grade.student.name} · {subject}.{' '}
                                {grade.category.label}: {grade.topic}, waga{' '}
                                {grade.weight}. Ocena zniknie z dziennika i
                                średniej. Usunięcie zostanie zapisane w historii
                                zmian z Twoim nazwiskiem.
                            </Dialog.Description>
                            <ErrorSummary
                                title="Nie udało się usunąć oceny"
                                errors={
                                    uncertain
                                        ? {
                                              network:
                                                  'Nie otrzymano odpowiedzi. Usunięcie mogło zostać zapisane. Sprawdź aktualny stan oceny przed kolejną operacją.',
                                          }
                                        : form.errors
                                }
                                fields={{
                                    reason: {
                                        id: 'delete-grade-reason',
                                        label: 'Powód usunięcia',
                                    },
                                }}
                            />
                            {uncertain && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.get(
                                            url,
                                            {},
                                            { preserveState: false },
                                        )
                                    }
                                >
                                    Sprawdź aktualny stan oceny
                                </Button>
                            )}
                            {'conflict' in form.errors && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="h-auto min-h-12 whitespace-normal"
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.get(
                                            window.location.pathname,
                                            {},
                                            { preserveState: false },
                                        )
                                    }
                                >
                                    Wczytaj aktualną ocenę i odrzuć wpisany
                                    powód
                                </Button>
                            )}
                            <FormField
                                id="delete-grade-reason"
                                label="Powód usunięcia (wymagany, trafi do historii)"
                                error={form.errors.reason}
                            >
                                {(a) => (
                                    <Input
                                        {...a}
                                        required
                                        minLength={3}
                                        maxLength={500}
                                        disabled={form.processing}
                                        value={form.data.reason}
                                        onChange={(e) =>
                                            form.setData(
                                                'reason',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>
                        <footer className="flex flex-col-reverse gap-4 px-5 pb-[max(1.75rem,env(safe-area-inset-bottom))] md:flex-row md:justify-end md:gap-2 md:rounded-b-xl md:border-t md:border-border md:bg-surface-secondary md:px-6 md:py-4">
                            <Button
                                ref={cancelRef}
                                type="button"
                                variant="secondary"
                                disabled={form.processing}
                                onClick={close}
                            >
                                Anuluj
                            </Button>
                            <SubmitButton
                                variant="destructive"
                                processing={form.processing}
                                disabled={
                                    uncertain || 'conflict' in form.errors
                                }
                            >
                                <Trash aria-hidden="true" />
                                Usuń ocenę
                            </SubmitButton>
                        </footer>
                    </form>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
