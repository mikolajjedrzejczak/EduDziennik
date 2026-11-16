import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { GradebookProps } from '@/types/gradebooks';

export function AssessmentDialog({
    assessment,
    categories,
    submitUrl,
    onClose,
    restoreFocus,
}: {
    assessment?: GradebookProps['assessments']['data'][number];
    categories: GradebookProps['categories'];
    submitUrl: string;
    onClose: () => void;
    restoreFocus: () => void;
}) {
    const cancelRef = useRef<HTMLButtonElement>(null);
    const form = useForm({
        category_id: '',
        topic: '',
        default_weight: '1',
        graded_on: '',
        confirmed: false,
    });
    const fields = {
        category_id: { id: 'assessment-category', label: 'Kategoria' },
        topic: { id: 'assessment-topic', label: 'Temat' },
        default_weight: { id: 'assessment-weight', label: 'Waga domyślna' },
        graded_on: { id: 'assessment-date', label: 'Data kolumny' },
        confirmed: { id: 'assessment-confirm', label: 'Potwierdzenie' },
    };
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                className="max-h-[calc(100svh-2rem)] overflow-y-auto bg-surface"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    restoreFocus();
                }}
                onOpenAutoFocus={
                    assessment
                        ? (e) => {
                              e.preventDefault();
                              cancelRef.current?.focus();
                          }
                        : undefined
                }
                onEscapeKeyDown={(e) => {
                    if (form.processing) e.preventDefault();
                }}
            >
                <DialogHeader>
                    <DialogTitle>
                        {assessment
                            ? 'Usuń pustą kolumnę'
                            : 'Dodaj kolumnę ocen'}
                    </DialogTitle>
                    <DialogDescription>
                        {assessment
                            ? `Usuniesz kolumnę „${assessment.topic}”. Kolumny z historią ocen pozostają w dzienniku.`
                            : 'Nowa kolumna będzie miała puste komórki. Średnie uczniów pozostaną bez zmian.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const options = {
                            preserveScroll: true,
                            onSuccess: onClose,
                        };
                        if (assessment) {
                            form.transform((data) => ({
                                confirmed: data.confirmed,
                            }));
                            form.delete(submitUrl, options);
                        } else {
                            form.transform(
                                ({
                                    category_id,
                                    topic,
                                    default_weight,
                                    graded_on,
                                }) => ({
                                    category_id,
                                    topic,
                                    default_weight,
                                    graded_on,
                                }),
                            );
                            form.post(submitUrl, options);
                        }
                    }}
                >
                    <ErrorSummary errors={form.errors} fields={fields} />
                    {assessment ? (
                        <FormField
                            id="assessment-confirm"
                            label="Potwierdzam usunięcie kolumny"
                            error={form.errors.confirmed}
                        >
                            {(attrs) => (
                                <input
                                    {...attrs}
                                    type="checkbox"
                                    className="size-5"
                                    checked={form.data.confirmed}
                                    onChange={(e) =>
                                        form.setData(
                                            'confirmed',
                                            e.target.checked,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                    ) : (
                        <>
                            <OptionField
                                id="assessment-category"
                                label="Kategoria"
                                options={categories}
                                value={form.data.category_id}
                                onChange={(v) => form.setData('category_id', v)}
                                error={form.errors.category_id}
                            />
                            <FormField
                                id="assessment-topic"
                                label="Temat"
                                error={form.errors.topic}
                            >
                                {(attrs) => (
                                    <Input
                                        {...attrs}
                                        maxLength={120}
                                        value={form.data.topic}
                                        onChange={(e) =>
                                            form.setData(
                                                'topic',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                id="assessment-weight"
                                label="Waga domyślna"
                                error={form.errors.default_weight}
                            >
                                {(attrs) => (
                                    <Input
                                        {...attrs}
                                        type="number"
                                        min={1}
                                        max={5}
                                        step={1}
                                        value={form.data.default_weight}
                                        onChange={(e) =>
                                            form.setData(
                                                'default_weight',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                id="assessment-date"
                                label="Data kolumny"
                                error={form.errors.graded_on}
                            >
                                {(attrs) => (
                                    <Input
                                        {...attrs}
                                        type="date"
                                        value={form.data.graded_on}
                                        onChange={(e) =>
                                            form.setData(
                                                'graded_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </>
                    )}
                    <div className="flex flex-wrap justify-end gap-3">
                        <Button
                            ref={cancelRef}
                            variant="secondary"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Anuluj
                        </Button>
                        <SubmitButton
                            processing={form.processing}
                            variant={assessment ? 'destructive' : 'default'}
                        >
                            {assessment ? 'Usuń kolumnę' : 'Dodaj kolumnę'}
                        </SubmitButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
