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
import type {
    ClassDTO,
    ClassManagementProps,
    EnrollmentDTO,
} from '@/types/school';

export type ManagementAction =
    | { kind: 'create' }
    | { kind: 'rename' | 'archive' | 'enroll'; schoolClass: ClassDTO }
    | {
          kind: 'dates' | 'transfer';
          schoolClass: ClassDTO;
          enrollment: EnrollmentDTO;
      };

const titles = {
    create: 'Dodaj klasę',
    rename: 'Zmień nazwę klasy',
    archive: 'Archiwizuj klasę',
    enroll: 'Dodaj ucznia do klasy',
    dates: 'Zmień daty członkostwa',
    transfer: 'Przenieś ucznia',
};

export function ManagementDialog({
    action,
    years,
    students,
    classOptions,
    defaultYear,
    onClose,
}: {
    action: ManagementAction;
    years: ClassManagementProps['years'];
    students: ClassManagementProps['students'];
    classOptions: ClassManagementProps['classOptions'];
    defaultYear: string;
    onClose: () => void;
}) {
    const enrollment = 'enrollment' in action ? action.enrollment : null;
    const schoolClass = 'schoolClass' in action ? action.schoolClass : null;
    const cancelRef = useRef<HTMLButtonElement>(null);
    const form = useForm({
        name: action.kind === 'rename' ? action.schoolClass.name : '',
        academic_year_id: defaultYear,
        student_id: enrollment?.student.id ?? '',
        starts_on: action.kind === 'dates' ? (enrollment?.startsOn ?? '') : '',
        ends_on: action.kind === 'dates' ? (enrollment?.endsOn ?? '') : '',
        target_class_id: '',
        transfer_from_enrollment_id:
            action.kind === 'transfer' ? action.enrollment.id : '',
        confirmed: false,
    });
    const classForm = action.kind === 'create' || action.kind === 'rename';
    const dates =
        action.kind === 'dates' ||
        action.kind === 'enroll' ||
        action.kind === 'transfer';
    const fields = {
        name: { id: 'class-name', label: 'Nazwa klasy' },
        academic_year_id: { id: 'class-year', label: 'Rok szkolny' },
        student_id: { id: 'enrollment-student', label: 'Uczeń' },
        starts_on: { id: 'enrollment-start', label: 'Data rozpoczęcia' },
        ends_on: { id: 'enrollment-end', label: 'Data zakończenia' },
        confirmed: { id: 'archive-confirm', label: 'Potwierdzenie' },
    };
    function submit(event: React.SubmitEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        switch (action.kind) {
            case 'create':
                form.transform((data) => ({
                    name: data.name,
                    academic_year_id: data.academic_year_id,
                }));
                form.post('/admin/classes', options);
                break;
            case 'rename':
                form.transform((data) => ({ name: data.name }));
                form.patch(`/admin/classes/${action.schoolClass.id}`, options);
                break;
            case 'archive':
                form.transform((data) => ({
                    confirmed: data.confirmed,
                }));
                form.delete(`/admin/classes/${action.schoolClass.id}`, options);
                break;
            case 'dates':
                form.transform((data) => ({
                    starts_on: data.starts_on,
                    ends_on: data.ends_on || null,
                }));
                form.patch(
                    `/admin/classes/${action.schoolClass.id}/enrollments/${action.enrollment.id}`,
                    options,
                );
                break;
            default:
                form.transform((data) => ({
                    student_id: data.student_id,
                    starts_on: data.starts_on,
                    ends_on: data.ends_on || null,
                    transfer_from_enrollment_id:
                        data.transfer_from_enrollment_id || null,
                }));
                form.post(
                    `/admin/classes/${action.kind === 'transfer' ? form.data.target_class_id : action.schoolClass.id}/enrollments`,
                    options,
                );
        }
    }
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                className="max-h-[calc(100svh-2rem)] overflow-y-auto bg-surface"
                onOpenAutoFocus={
                    action.kind === 'archive'
                        ? (event) => {
                              event.preventDefault();
                              cancelRef.current?.focus();
                          }
                        : undefined
                }
                onEscapeKeyDown={(event) => {
                    if (form.processing) event.preventDefault();
                }}
            >
                <DialogHeader>
                    <DialogTitle>{titles[action.kind]}</DialogTitle>
                    <DialogDescription>
                        {enrollment
                            ? `${enrollment.student.label} · ${schoolClass?.name}`
                            : schoolClass
                              ? `Klasa ${schoolClass.name} · ${schoolClass.academicYear.label}`
                              : 'Utwórz klasę w wybranym roku szkolnym.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    noValidate
                    className="space-y-4"
                    aria-busy={form.processing}
                >
                    <ErrorSummary errors={form.errors} fields={fields} />
                    <fieldset
                        disabled={form.processing}
                        className="min-w-0 space-y-4"
                    >
                        {classForm && (
                            <FormField
                                id="class-name"
                                label="Nazwa klasy"
                                error={form.errors.name}
                            >
                                {(attributes) => (
                                    <Input
                                        {...attributes}
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={20}
                                        autoComplete="off"
                                    />
                                )}
                            </FormField>
                        )}
                        {action.kind === 'create' && (
                            <OptionField
                                id="class-year"
                                label="Rok szkolny"
                                value={form.data.academic_year_id}
                                options={years}
                                onChange={(value) =>
                                    form.setData('academic_year_id', value)
                                }
                                error={form.errors.academic_year_id}
                            />
                        )}
                        {action.kind === 'enroll' && (
                            <OptionField
                                id="enrollment-student"
                                label="Uczeń"
                                value={form.data.student_id}
                                options={students}
                                onChange={(value) =>
                                    form.setData('student_id', value)
                                }
                                error={form.errors.student_id}
                            />
                        )}
                        {action.kind === 'transfer' && (
                            <>
                                <OptionField
                                    id="transfer-target"
                                    label="Klasa docelowa"
                                    value={form.data.target_class_id}
                                    options={classOptions.filter(
                                        (option) =>
                                            option.id !== schoolClass?.id,
                                    )}
                                    onChange={(value) =>
                                        form.setData('target_class_id', value)
                                    }
                                />
                                <p className="text-sm text-text-secondary">
                                    Poprzednie członkostwo zakończy się dzień
                                    przed przeniesieniem. Dotychczasowe oceny
                                    pozostaną w poprzedniej klasie.
                                </p>
                            </>
                        )}
                        {dates && (
                            <>
                                <FormField
                                    id="enrollment-start"
                                    label={
                                        action.kind === 'transfer'
                                            ? 'Data przeniesienia'
                                            : 'Data rozpoczęcia'
                                    }
                                    error={form.errors.starts_on}
                                >
                                    {(attributes) => (
                                        <Input
                                            {...attributes}
                                            type="date"
                                            value={form.data.starts_on}
                                            onChange={(event) =>
                                                form.setData(
                                                    'starts_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="enrollment-end"
                                    label="Data zakończenia"
                                    hint="Puste pole oznacza koniec roku szkolnego."
                                    error={form.errors.ends_on}
                                >
                                    {(attributes) => (
                                        <Input
                                            {...attributes}
                                            type="date"
                                            value={form.data.ends_on}
                                            onChange={(event) =>
                                                form.setData(
                                                    'ends_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            </>
                        )}
                        {action.kind === 'archive' && (
                            <>
                                <p className="text-sm text-text-secondary">
                                    Archiwizacja zachowuje historię. Wymaga
                                    zakończenia członkostw i cofnięcia
                                    przydziałów nauczycieli.
                                </p>
                                <FormField
                                    id="archive-confirm"
                                    label="Potwierdzam archiwizację klasy"
                                    error={form.errors.confirmed}
                                >
                                    {(attributes) => (
                                        <input
                                            {...attributes}
                                            type="checkbox"
                                            className="size-5 accent-primary"
                                            checked={form.data.confirmed}
                                            onChange={(event) =>
                                                form.setData(
                                                    'confirmed',
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            </>
                        )}
                    </fieldset>
                    <div className="flex flex-wrap justify-end gap-2">
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
                            variant={
                                action.kind === 'archive'
                                    ? 'destructive'
                                    : 'primary'
                            }
                            disabled={
                                action.kind === 'transfer' &&
                                !form.data.target_class_id
                            }
                        >
                            {action.kind === 'archive'
                                ? 'Archiwizuj'
                                : 'Zapisz'}
                        </SubmitButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
