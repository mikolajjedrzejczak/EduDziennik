import { useForm } from '@inertiajs/react';
import TeacherAssignmentController from '@/actions/App/Http/Controllers/Admin/TeacherAssignmentController';
import { ErrorSummary } from '@/components/forms/error-summary';
import { SubmitButton } from '@/components/forms/submit-button';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types/school';

const fields = {
    teacher_id: { id: 'assignment-teacher', label: 'Nauczyciel' },
    school_class_id: { id: 'assignment-class', label: 'Klasa' },
    subject_id: { id: 'assignment-subject', label: 'Przedmiot' },
    term_id: { id: 'assignment-term', label: 'Semestr' },
};

/** Grant form. All consistency rules (active teacher, term of the class year) are decided by the server. */
export function AssignmentForm({
    teachers,
    classes,
    subjects,
    terms,
    onDone,
    onCancel,
}: {
    teachers: Option[];
    classes: Option[];
    subjects: Option[];
    terms: Option[];
    onDone: () => void;
    onCancel: () => void;
}) {
    const form = useForm({
        teacher_id: '',
        school_class_id: '',
        subject_id: '',
        term_id: '',
    });

    return (
        <form
            noValidate
            aria-busy={form.processing}
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (form.processing) return;
                form.post(TeacherAssignmentController.store.url(), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <ErrorSummary errors={form.errors} fields={fields} />
            <OptionField
                {...fields.teacher_id}
                value={form.data.teacher_id}
                options={teachers}
                error={form.errors.teacher_id}
                onChange={(value) => form.setData('teacher_id', value)}
            />
            <OptionField
                {...fields.school_class_id}
                value={form.data.school_class_id}
                options={classes}
                error={form.errors.school_class_id}
                onChange={(value) => form.setData('school_class_id', value)}
            />
            <OptionField
                {...fields.subject_id}
                value={form.data.subject_id}
                options={subjects}
                error={form.errors.subject_id}
                onChange={(value) => form.setData('subject_id', value)}
            />
            <OptionField
                {...fields.term_id}
                value={form.data.term_id}
                options={terms}
                error={form.errors.term_id}
                onChange={(value) => form.setData('term_id', value)}
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
                    className="w-full md:w-auto"
                >
                    Przypisz nauczyciela
                </SubmitButton>
            </div>
        </form>
    );
}
