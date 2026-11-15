import { Plus, Trash2 } from 'lucide-react';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import type { Option } from '@/types/school';
import type { YearOption } from '@/types/users';

/** `uid` is a client-only React key and is stripped before the request is sent. */
export type AssignmentRow = {
    uid: string;
    school_class_id: string;
    subject_id: string;
    term_id: string;
};

export const MAX_ASSIGNMENTS = 20;
export const newAssignment = (): AssignmentRow => ({
    uid: crypto.randomUUID(),
    school_class_id: '',
    subject_id: '',
    term_id: '',
});

/** Repeatable teaching-scope rows of the create form; consistency (term of the class year, open term) is decided by the server. */
export function AssignmentRows({
    rows,
    errors,
    subjects,
    classes,
    terms,
    onChange,
}: {
    rows: AssignmentRow[];
    errors: Readonly<Record<string, string | undefined>>;
    subjects: Option[];
    classes: YearOption[];
    terms: YearOption[];
    onChange: (rows: AssignmentRow[]) => void;
}) {
    const update = (index: number, patch: Partial<AssignmentRow>) =>
        onChange(
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );

    return (
        <div className="space-y-4">
            {rows.length === 0 && (
                <p className="rounded-md border border-dashed border-border p-3 text-sm text-text-secondary">
                    Nauczyciel bez przydziałów jest dozwolony — zakres można
                    nadać później.
                </p>
            )}
            {rows.map((row, index) => {
                const key = `assignments.${index}`;
                const classTerms = terms.filter(
                    (term) =>
                        !row.school_class_id ||
                        term.academicYearId ===
                            classes.find((c) => c.id === row.school_class_id)
                                ?.academicYearId,
                );
                return (
                    <fieldset
                        key={row.uid}
                        className="grid gap-3 rounded-md border border-border p-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-start"
                    >
                        <legend className="px-1 text-xs text-text-secondary">
                            Przydział {index + 1}
                        </legend>
                        <OptionField
                            id={`assignment-subject-${index}`}
                            label="Przedmiot"
                            value={row.subject_id}
                            options={subjects}
                            error={errors[`${key}.subject_id`]}
                            onChange={(value) =>
                                update(index, { subject_id: value })
                            }
                        />
                        <OptionField
                            id={`assignment-class-${index}`}
                            label="Klasa"
                            value={row.school_class_id}
                            options={classes}
                            error={errors[`${key}.school_class_id`]}
                            onChange={(value) =>
                                update(index, {
                                    school_class_id: value,
                                    term_id: '',
                                })
                            }
                        />
                        <OptionField
                            id={`assignment-term-${index}`}
                            label="Semestr"
                            value={row.term_id}
                            options={classTerms}
                            error={errors[`${key}.term_id`] ?? errors[key]}
                            onChange={(value) =>
                                update(index, { term_id: value })
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11 text-error md:mt-[22px]"
                            aria-label={`Usuń przydział ${index + 1}`}
                            onClick={() =>
                                onChange(rows.filter((_, i) => i !== index))
                            }
                        >
                            <Trash2 aria-hidden="true" className="size-4" />
                        </Button>
                    </fieldset>
                );
            })}
            {errors.assignments && (
                <p role="alert" className="text-sm text-error">
                    {errors.assignments}
                </p>
            )}
            <Button
                type="button"
                variant="secondary"
                disabled={rows.length >= MAX_ASSIGNMENTS}
                onClick={() => onChange([...rows, newAssignment()])}
            >
                <Plus aria-hidden="true" className="size-4" />
                Dodaj przydział
            </Button>
        </div>
    );
}
