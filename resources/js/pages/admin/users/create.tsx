import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    AssignmentRows,
    type AssignmentRow,
} from '@/components/admin/assignment-rows';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import PasswordInput from '@/components/password-input';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { roleLabels } from '@/lib/users';
import { cn } from '@/lib/utils';
import type { AccountStatus, Role, UserFormProps } from '@/types/users';

const summaryFields = {
    first_name: { id: 'user-first-name', label: 'Imię' },
    last_name: { id: 'user-last-name', label: 'Nazwisko' },
    email: { id: 'user-email', label: 'Adres e-mail' },
    role: { id: 'user-role-student', label: 'Rola' },
    status: { id: 'user-status-active', label: 'Status' },
    password: { id: 'user-password', label: 'Hasło' },
    'enrollment.school_class_id': { id: 'enrollment-class', label: 'Klasa' },
    'enrollment.starts_on': { id: 'enrollment-starts', label: 'Początek' },
    'enrollment.ends_on': { id: 'enrollment-ends', label: 'Koniec' },
    enrollment: { id: 'enrollment-class', label: 'Członkostwo w klasie' },
    assignments: { id: 'assignment-subject-0', label: 'Przydziały' },
};

const roleHints: Record<Role, string> = {
    student: 'Widzi tylko własne oceny i historię zmian.',
    teacher: 'Wystawia i zmienia oceny w przydzielonych klasach.',
    admin: 'Zarządza kontami, klasami i przydziałami.',
};

const statuses: { value: AccountStatus; label: string }[] = [
    { value: 'active', label: 'Aktywne' },
    { value: 'inactive', label: 'Nieaktywne' },
];

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4 border-b border-border py-6 md:grid-cols-[240px_1fr] md:gap-10 xl:grid-cols-[292px_1fr]">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 text-xs text-text-secondary">
                    {description}
                </p>
            </div>
            <div className="grid min-w-0 gap-4">{children}</div>
        </section>
    );
}

function Choice({
    name,
    value,
    checked,
    label,
    hint,
    id,
    onSelect,
}: {
    name: string;
    value: string;
    checked: boolean;
    label: string;
    hint?: string;
    id: string;
    onSelect: () => void;
}) {
    return (
        <label
            htmlFor={id}
            className={cn(
                'flex min-h-11 cursor-pointer flex-col gap-1 rounded-md border bg-surface p-3 has-focus-visible:outline-2 has-focus-visible:outline-focus',
                checked
                    ? 'border-[1.5px] border-primary bg-primary-subtle'
                    : 'border-border-strong',
            )}
        >
            <span className="flex items-center gap-2 text-sm font-medium">
                <input
                    id={id}
                    type="radio"
                    name={name}
                    value={value}
                    checked={checked}
                    onChange={onSelect}
                    className="size-4 accent-primary"
                />
                {label}
            </span>
            {hint && (
                <span className="text-xs text-text-secondary">{hint}</span>
            )}
        </label>
    );
}

export default function CreateUser({ options }: UserFormProps) {
    const form = useForm<{
        first_name: string;
        last_name: string;
        email: string;
        role: Role;
        status: AccountStatus;
        password: string;
        password_confirmation: string;
        enrollment_class: string;
        enrollment_starts_on: string;
        enrollment_ends_on: string;
        assignments: AssignmentRow[];
    }>({
        first_name: '',
        last_name: '',
        email: '',
        role: 'student',
        status: 'active',
        password: '',
        password_confirmation: '',
        enrollment_class: '',
        enrollment_starts_on: '',
        enrollment_ends_on: '',
        assignments: [],
    });
    const { data } = form;
    // Nested server keys (enrollment.*, assignments.N.*) are not part of the flat form type.
    const errors: Readonly<Record<string, string | undefined>> = form.errors;
    const errorCount = Object.values(errors).filter(Boolean).length;
    const relationsAllowed = data.status === 'active';

    function submit() {
        if (form.processing) return;
        // Only the context of the selected role is sent; the server re-validates role/relation consistency.
        form.transform((current) => ({
            first_name: current.first_name,
            last_name: current.last_name,
            email: current.email,
            role: current.role,
            status: current.status,
            password: current.password,
            password_confirmation: current.password_confirmation,
            enrollment:
                current.role === 'student' &&
                current.status === 'active' &&
                current.enrollment_class
                    ? {
                          school_class_id: current.enrollment_class,
                          starts_on: current.enrollment_starts_on,
                          ends_on: current.enrollment_ends_on || null,
                      }
                    : null,
            assignments:
                current.role === 'teacher' && current.status === 'active'
                    ? current.assignments.map((row) => ({
                          school_class_id: row.school_class_id,
                          subject_id: row.subject_id,
                          term_id: row.term_id,
                      }))
                    : null,
        }));
        form.post('/admin/users', {
            preserveScroll: true,
            onError: () => form.reset('password', 'password_confirmation'),
        });
    }

    const text = (
        field: 'first_name' | 'last_name' | 'email',
        id: string,
        label: string,
        extra: { type?: string; autoComplete: string; hint?: string },
    ) => (
        <FormField
            id={id}
            label={label}
            error={errors[field]}
            hint={extra.hint}
        >
            {(attributes) => (
                <Input
                    {...attributes}
                    type={extra.type ?? 'text'}
                    value={data[field]}
                    autoComplete={extra.autoComplete}
                    required
                    onChange={(event) =>
                        form.setData(field, event.target.value)
                    }
                />
            )}
        </FormField>
    );

    return (
        <>
            <Head title="Dodaj użytkownika" />
            <form
                noValidate
                aria-busy={form.processing}
                className="p-4 md:p-6 xl:px-10 xl:py-7"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                <Link
                    href="/admin/users"
                    className="inline-flex min-h-11 items-center gap-1 text-sm text-primary underline outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Użytkownicy
                </Link>
                <header className="mb-4">
                    <h1 className="text-2xl leading-8 font-semibold">
                        Dodaj użytkownika
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        Konto powstaje razem z opcjonalnym kontekstem
                        dydaktycznym — albo w całości, albo wcale. Hasło przekaż
                        użytkownikowi bezpiecznym kanałem.
                    </p>
                </header>
                <ErrorSummary
                    errors={errors}
                    fields={summaryFields}
                    title={`Popraw ${errorCount} ${errorCount === 1 ? 'pole' : 'pola'}, aby utworzyć konto.`}
                />

                <Section
                    title="Dane osobowe"
                    description="Imię i nazwisko widzą uczniowie i nauczyciele."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {text('first_name', 'user-first-name', 'Imię', {
                            autoComplete: 'off',
                        })}
                        {text('last_name', 'user-last-name', 'Nazwisko', {
                            autoComplete: 'off',
                        })}
                    </div>
                    {text('email', 'user-email', 'Adres e-mail (login)', {
                        type: 'email',
                        autoComplete: 'off',
                        hint: 'Adres musi być unikalny, także względem usuniętych kont.',
                    })}
                </Section>

                <Section
                    title="Rola i status"
                    description="Rola decyduje o tym, co użytkownik może zobaczyć i zmienić."
                >
                    <fieldset
                        aria-describedby={
                            errors.role ? 'user-role-error' : undefined
                        }
                    >
                        <legend className="mb-2 text-[13px]">Rola</legend>
                        <div className="grid gap-3 md:grid-cols-3">
                            {(Object.keys(roleLabels) as Role[]).map((role) => (
                                <Choice
                                    key={role}
                                    id={`user-role-${role}`}
                                    name="role"
                                    value={role}
                                    checked={data.role === role}
                                    label={roleLabels[role]}
                                    hint={roleHints[role]}
                                    onSelect={() => form.setData('role', role)}
                                />
                            ))}
                        </div>
                        {errors.role && (
                            <p
                                id="user-role-error"
                                className="mt-1 text-xs text-error"
                            >
                                {errors.role}
                            </p>
                        )}
                    </fieldset>
                    <fieldset>
                        <legend className="mb-2 text-[13px]">
                            Status konta
                        </legend>
                        <div className="grid gap-3 md:grid-cols-3">
                            {statuses.map((status) => (
                                <Choice
                                    key={status.value}
                                    id={`user-status-${status.value}`}
                                    name="status"
                                    value={status.value}
                                    checked={data.status === status.value}
                                    label={status.label}
                                    onSelect={() =>
                                        form.setData('status', status.value)
                                    }
                                />
                            ))}
                        </div>
                        {errors.status && (
                            <p className="mt-1 text-xs text-error">
                                {errors.status}
                            </p>
                        )}
                    </fieldset>
                </Section>

                {data.role === 'student' && (
                    <Section
                        title="Klasa ucznia"
                        description="Opcjonalne. Uczeń bez klasy zobaczy pusty stan do czasu dodania członkostwa."
                    >
                        {!relationsAllowed && (
                            <p
                                role="status"
                                className="text-sm text-text-secondary"
                            >
                                Członkostwo wymaga aktywnego konta.
                            </p>
                        )}
                        <OptionField
                            id="enrollment-class"
                            label="Klasa"
                            optional
                            noneLabel="Bez klasy"
                            value={data.enrollment_class}
                            options={options.classes}
                            error={
                                errors['enrollment.school_class_id'] ??
                                errors.enrollment
                            }
                            onChange={(value) =>
                                form.setData('enrollment_class', value)
                            }
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="enrollment-starts"
                                label="Początek członkostwa"
                                error={errors['enrollment.starts_on']}
                            >
                                {(attributes) => (
                                    <Input
                                        {...attributes}
                                        type="date"
                                        value={data.enrollment_starts_on}
                                        onChange={(event) =>
                                            form.setData(
                                                'enrollment_starts_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                id="enrollment-ends"
                                label="Koniec członkostwa (opcjonalnie)"
                                error={errors['enrollment.ends_on']}
                            >
                                {(attributes) => (
                                    <Input
                                        {...attributes}
                                        type="date"
                                        value={data.enrollment_ends_on}
                                        onChange={(event) =>
                                            form.setData(
                                                'enrollment_ends_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>
                    </Section>
                )}

                {data.role === 'teacher' && (
                    <Section
                        title="Przydziały nauczyciela"
                        description="Przedmiot, klasa i semestr, w których nauczyciel może wystawiać oceny."
                    >
                        {!relationsAllowed && (
                            <p
                                role="status"
                                className="text-sm text-text-secondary"
                            >
                                Przydziały wymagają aktywnego konta.
                            </p>
                        )}
                        <AssignmentRows
                            rows={data.assignments}
                            errors={errors}
                            subjects={options.subjects}
                            classes={options.classes}
                            terms={options.terms}
                            onChange={(rows) =>
                                form.setData('assignments', rows)
                            }
                        />
                    </Section>
                )}

                <Section
                    title="Hasło startowe"
                    description="12–128 znaków. Hasło przechowujemy wyłącznie jako skrót."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            id="user-password"
                            label="Hasło"
                            error={errors.password}
                        >
                            {(attributes) => (
                                <PasswordInput
                                    {...attributes}
                                    value={data.password}
                                    autoComplete="new-password"
                                    required
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            id="user-password-confirmation"
                            label="Powtórz hasło"
                        >
                            {(attributes) => (
                                <PasswordInput
                                    {...attributes}
                                    value={data.password_confirmation}
                                    autoComplete="new-password"
                                    required
                                    onChange={(event) =>
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                    </div>
                </Section>

                <div className="flex flex-col-reverse gap-2 py-6 md:flex-row md:justify-end">
                    <Button
                        variant="secondary"
                        asChild
                        className="h-12 md:h-10"
                    >
                        <Link href="/admin/users">Anuluj</Link>
                    </Button>
                    <SubmitButton processing={form.processing}>
                        Utwórz konto
                    </SubmitButton>
                </div>
            </form>
        </>
    );
}
