import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Head, router, useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import Gradebook from '@/pages/shared/gradebooks/show';
import type { GradeFormProps } from '@/types/gradebooks';

const fields = {
    student_enrollment_id: { id: 'grade-student', label: 'Uczeń' },
    assessment_id: { id: 'grade-assessment', label: 'Kolumna' },
    value: { id: 'grade-value', label: 'Ocena' },
    category_id: { id: 'grade-category', label: 'Kategoria' },
    weight: { id: 'grade-weight', label: 'Waga' },
    topic: { id: 'grade-topic', label: 'Temat' },
    graded_on: { id: 'grade-date', label: 'Data' },
    comment: { id: 'grade-comment', label: 'Komentarz' },
};

export default function CreateGrade(props: GradeFormProps) {
    const {
        assessment,
        selectedEnrollmentId,
        assessments,
        students,
        context,
        categories,
    } = props;
    const initial = assessment ?? assessments[0];
    const form = useForm({
        assignment_id: context.assignmentId ?? '',
        assessment_id: initial?.id ?? '',
        student_enrollment_id: selectedEnrollmentId ?? '',
        value: '',
        category_id: initial?.category.id ?? '',
        weight: String(initial?.defaultWeight ?? 1),
        topic: initial?.topic ?? '',
        graded_on: initial?.gradedOn ?? '',
        comment: '',
    });
    const submittingRef = useRef(false);
    const empty =
        students.length === 0 ||
        assessments.length === 0 ||
        categories.length === 0;
    const student = students.find(
        (s) => s.enrollmentId === form.data.student_enrollment_id,
    );
    function close() {
        if (form.processing || submittingRef.current) return;
        router.get(
            props.cancelUrl,
            {},
            {
                onSuccess: () => {
                    const links =
                        document.querySelectorAll<HTMLAnchorElement>(
                            'a[aria-label]',
                        );
                    const original = Array.from(links).find(
                        (a) =>
                            a.getClientRects().length > 0 &&
                            a.href.includes(
                                `student_enrollment_id=${selectedEnrollmentId}&assessment_id=${assessment?.id}`,
                            ),
                    );
                    original?.focus();
                },
            },
        );
    }
    return (
        <>
            <Gradebook {...props.background} />
            <Head title="Wystaw ocenę" />
            <DialogPrimitive.Root
                open
                onOpenChange={(open) => {
                    if (!open) close();
                }}
            >
                <DialogPrimitive.Portal>
                    <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-nav-bg/45" />
                    <DialogPrimitive.Content
                        className="fixed inset-x-0 bottom-0 z-50 flex max-h-[95dvh] flex-col rounded-t-2xl bg-surface shadow-overlay outline-none md:inset-y-0 md:right-0 md:left-auto md:h-dvh md:max-h-dvh md:w-[480px] md:rounded-none"
                        onCloseAutoFocus={(e) => e.preventDefault()}
                        onInteractOutside={(e) => e.preventDefault()}
                        onEscapeKeyDown={(e) => {
                            if (form.processing) e.preventDefault();
                        }}
                    >
                        <div
                            aria-hidden="true"
                            className="mx-auto mt-2 h-1 w-9 rounded bg-border-strong md:hidden"
                        />
                        <header className="flex shrink-0 items-start justify-between gap-2 border-b border-border px-5 py-4 md:px-6 md:py-5">
                            <div className="min-w-0">
                                <DialogPrimitive.Title className="text-lg font-semibold">
                                    Wystaw ocenę
                                </DialogPrimitive.Title>
                                <DialogPrimitive.Description className="mt-0.5 text-[13px] wrap-anywhere text-text-secondary">
                                    {student ? `${student.name}, ` : ''}
                                    {context.schoolClass.label} ·{' '}
                                    {context.subject.label}
                                </DialogPrimitive.Description>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Zamknij formularz"
                                disabled={form.processing}
                                onClick={close}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </header>
                        <form
                            noValidate
                            className="flex min-h-0 flex-1 flex-col"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (
                                    submittingRef.current ||
                                    form.processing ||
                                    empty
                                )
                                    return;
                                submittingRef.current = true;
                                form.post(props.submitUrl, {
                                    preserveScroll: true,
                                    onFinish: () => {
                                        submittingRef.current = false;
                                    },
                                });
                            }}
                        >
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5 md:space-y-[18px] md:px-6">
                                <ErrorSummary
                                    errors={form.errors}
                                    fields={fields}
                                />
                                {empty ? (
                                    <p role="status">
                                        Brak uczniów, kolumn lub aktywnych
                                        kategorii. Wróć do dziennika i uzupełnij
                                        kontekst.
                                    </p>
                                ) : (
                                    <>
                                        {(!selectedEnrollmentId ||
                                            !assessment) && (
                                            <>
                                                <OptionField
                                                    id="grade-student"
                                                    label="Uczeń"
                                                    value={
                                                        form.data
                                                            .student_enrollment_id
                                                    }
                                                    options={students.map(
                                                        (s) => ({
                                                            id: s.enrollmentId,
                                                            label: s.name,
                                                        }),
                                                    )}
                                                    error={
                                                        form.errors
                                                            .student_enrollment_id
                                                    }
                                                    onChange={(v) =>
                                                        form.setData(
                                                            'student_enrollment_id',
                                                            v,
                                                        )
                                                    }
                                                />
                                                <OptionField
                                                    id="grade-assessment"
                                                    label="Kolumna"
                                                    value={
                                                        form.data.assessment_id
                                                    }
                                                    options={assessments.map(
                                                        (a) => ({
                                                            id: a.id,
                                                            label: `${a.topic} · ${a.gradedOn}`,
                                                        }),
                                                    )}
                                                    error={
                                                        form.errors
                                                            .assessment_id
                                                    }
                                                    onChange={(v) => {
                                                        const a =
                                                            assessments.find(
                                                                (a) =>
                                                                    a.id === v,
                                                            );
                                                        if (a)
                                                            form.setData({
                                                                ...form.data,
                                                                assessment_id:
                                                                    a.id,
                                                                category_id:
                                                                    a.category
                                                                        .id,
                                                                weight: String(
                                                                    a.defaultWeight,
                                                                ),
                                                                topic: a.topic,
                                                                graded_on:
                                                                    a.gradedOn,
                                                            });
                                                    }}
                                                />
                                            </>
                                        )}
                                        <fieldset className="space-y-2">
                                            <legend className="text-[13px] font-medium">
                                                Ocena
                                            </legend>
                                            <div
                                                id="grade-value"
                                                aria-invalid={
                                                    form.errors.value
                                                        ? true
                                                        : undefined
                                                }
                                                aria-describedby={
                                                    form.errors.value
                                                        ? 'grade-value-error'
                                                        : undefined
                                                }
                                                role="radiogroup"
                                                aria-label="Ocena"
                                                tabIndex={-1}
                                                className="grid grid-cols-6 gap-1.5 md:gap-2"
                                                onKeyDown={(e) => {
                                                    if (/^[1-6]$/.test(e.key)) {
                                                        e.preventDefault();
                                                        form.setData(
                                                            'value',
                                                            e.key,
                                                        );
                                                        e.currentTarget
                                                            .querySelector<HTMLInputElement>(
                                                                `input[value="${e.key}"]`,
                                                            )
                                                            ?.focus();
                                                    }
                                                }}
                                            >
                                                {[1, 2, 3, 4, 5, 6].map((n) => (
                                                    <label
                                                        key={n}
                                                        className="relative min-w-0 cursor-pointer"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="value"
                                                            value={n}
                                                            checked={
                                                                form.data
                                                                    .value ===
                                                                String(n)
                                                            }
                                                            onChange={(e) =>
                                                                form.setData(
                                                                    'value',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="peer absolute inset-0 m-0 h-full w-full cursor-pointer opacity-0"
                                                        />
                                                        <span
                                                            className={cn(
                                                                'flex h-[52px] items-center justify-center rounded-lg border border-border-strong text-2xl font-semibold peer-checked:border-2 peer-checked:border-primary peer-checked:bg-primary-subtle peer-checked:text-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus',
                                                                form.errors
                                                                    .value &&
                                                                    'border-error',
                                                            )}
                                                        >
                                                            {n}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                            <InputError
                                                id="grade-value-error"
                                                message={form.errors.value}
                                            />
                                        </fieldset>
                                        <div className="grid grid-cols-2 gap-3">
                                            <OptionField
                                                id="grade-category"
                                                label="Kategoria"
                                                value={form.data.category_id}
                                                options={categories}
                                                error={form.errors.category_id}
                                                onChange={(v) =>
                                                    form.setData(
                                                        'category_id',
                                                        v,
                                                    )
                                                }
                                            />
                                            <OptionField
                                                id="grade-weight"
                                                label="Waga"
                                                value={form.data.weight}
                                                options={[1, 2, 3, 4, 5].map(
                                                    (n) => ({
                                                        id: String(n),
                                                        label: String(n),
                                                    }),
                                                )}
                                                error={form.errors.weight}
                                                onChange={(v) =>
                                                    form.setData('weight', v)
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-2 md:gap-3">
                                            <FormField
                                                id="grade-topic"
                                                label="Temat"
                                                error={form.errors.topic}
                                            >
                                                {(a) => (
                                                    <Input
                                                        {...a}
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
                                                id="grade-date"
                                                label="Data"
                                                error={form.errors.graded_on}
                                            >
                                                {(a) => (
                                                    <Input
                                                        {...a}
                                                        type="date"
                                                        value={
                                                            form.data.graded_on
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'graded_on',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </FormField>
                                        </div>
                                        <FormField
                                            id="grade-comment"
                                            label="Komentarz (widoczny dla ucznia)"
                                            error={form.errors.comment}
                                        >
                                            {(a) => (
                                                <Input
                                                    {...a}
                                                    maxLength={500}
                                                    value={form.data.comment}
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'comment',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                    </>
                                )}
                            </div>
                            <footer className="flex shrink-0 flex-col-reverse gap-2 border-t border-border px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:flex-row md:justify-end md:px-6">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={form.processing}
                                    onClick={close}
                                >
                                    Anuluj
                                </Button>
                                <SubmitButton
                                    processing={form.processing}
                                    disabled={empty}
                                >
                                    Zapisz ocenę
                                </SubmitButton>
                            </footer>
                        </form>
                    </DialogPrimitive.Content>
                </DialogPrimitive.Portal>
            </DialogPrimitive.Root>
        </>
    );
}
