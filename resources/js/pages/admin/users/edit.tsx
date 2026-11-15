import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import { RoleBadge, StatusBadge } from '@/components/admin/user-badges';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import PasswordInput from '@/components/password-input';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatDateTime, fullName, roleLabels } from '@/lib/users';
import type { AccountStatus, Role, UserFormProps } from '@/types/users';

const fields = {
    first_name: { id: 'user-first-name', label: 'Imię' },
    last_name: { id: 'user-last-name', label: 'Nazwisko' },
    email: { id: 'user-email', label: 'Adres e-mail (login)' },
    role: { id: 'user-role', label: 'Rola' },
    status: { id: 'user-status', label: 'Status konta' },
    password: { id: 'user-password', label: 'Nowe hasło (opcjonalnie)' },
    password_confirmation: {
        id: 'user-password-confirmation',
        label: 'Powtórz nowe hasło',
    },
};

export default function EditUser({
    user,
    options,
}: UserFormProps & { user: NonNullable<UserFormProps['user']> }) {
    const form = useForm({
        first_name: user.firstName,
        last_name: user.lastName,
        email: user.email,
        role: user.role,
        status: user.status,
        password: '',
        password_confirmation: '',
    });
    const showUrl = `/admin/users/${user.id}`;

    return (
        <>
            <Head title={`Edytuj użytkownika — ${fullName(user)}`} />
            <form
                noValidate
                aria-busy={form.processing}
                className="space-y-6 p-4 md:p-6 xl:px-10 xl:py-7"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing) return;
                    form.patch(showUrl, {
                        preserveScroll: true,
                        onFinish: () =>
                            form.reset('password', 'password_confirmation'),
                    });
                }}
            >
                <Link
                    href={showUrl}
                    className="inline-flex min-h-11 items-center gap-1 text-sm text-primary underline focus-visible:ring-[3px] focus-visible:ring-focus"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                    Szczegóły użytkownika
                </Link>
                <header className="flex items-center gap-4">
                    <div
                        aria-hidden="true"
                        className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary-muted font-semibold text-primary-hover md:size-16"
                    >
                        {user.firstName.charAt(0)}
                        {user.lastName.charAt(0)}
                    </div>
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="text-xs text-text-secondary">
                            Edytuj użytkownika
                        </p>
                        <h1 className="text-2xl leading-8 font-semibold wrap-anywhere">
                            {fullName(user)}
                        </h1>
                        <div className="flex flex-wrap gap-2">
                            <RoleBadge role={user.role} />
                            <StatusBadge user={user} />
                        </div>
                        <p className="text-xs text-text-secondary">
                            Ostatnie logowanie:{' '}
                            {formatDateTime(user.lastLoginAt)}
                        </p>
                    </div>
                </header>
                <div className="border-b border-border py-2 text-sm font-medium">
                    Dane i rola
                </div>
                <ErrorSummary errors={form.errors} fields={fields} />
                <div className="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,440px)]">
                    <section
                        aria-labelledby="personal-data"
                        className="min-w-0 space-y-4"
                    >
                        <h2
                            id="personal-data"
                            className="text-lg font-semibold"
                        >
                            Dane osobowe
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {(['first_name', 'last_name'] as const).map(
                                (key) => (
                                    <FormField
                                        key={key}
                                        {...fields[key]}
                                        error={form.errors[key]}
                                    >
                                        {(attributes) => (
                                            <Input
                                                {...attributes}
                                                required
                                                maxLength={80}
                                                autoComplete="off"
                                                value={form.data[key]}
                                                onChange={(event) =>
                                                    form.setData(
                                                        key,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>
                                ),
                            )}
                        </div>
                        <FormField {...fields.email} error={form.errors.email}>
                            {(attributes) => (
                                <Input
                                    {...attributes}
                                    required
                                    type="email"
                                    maxLength={254}
                                    autoComplete="off"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                        </FormField>
                        <div className="grid gap-4 md:grid-cols-2">
                            <OptionField
                                {...fields.role}
                                value={form.data.role}
                                options={options.roles.map((role) => ({
                                    id: role,
                                    label: roleLabels[role],
                                }))}
                                error={form.errors.role}
                                onChange={(value) =>
                                    form.setData('role', value as Role)
                                }
                            />
                            <OptionField
                                {...fields.status}
                                value={form.data.status}
                                options={[
                                    { id: 'active', label: 'Aktywne' },
                                    { id: 'inactive', label: 'Nieaktywne' },
                                ]}
                                error={form.errors.status}
                                onChange={(value) =>
                                    form.setData(
                                        'status',
                                        value as AccountStatus,
                                    )
                                }
                            />
                        </div>
                        <p className="flex items-start gap-2 rounded-md bg-warning-bg px-3.5 py-3 text-[13px] leading-[18px]">
                            <TriangleAlert
                                aria-hidden="true"
                                className="size-[18px] shrink-0 text-warning"
                            />
                            Zmiana roli konta z historycznymi członkostwami lub
                            przydziałami jest zablokowana. Zmiana roli, statusu
                            lub reset hasła wyloguje użytkownika ze wszystkich
                            sesji.
                        </p>
                    </section>
                    <aside className="space-y-3 rounded-lg border border-border bg-surface p-4">
                        <h2 className="text-lg font-semibold">
                            Relacje dydaktyczne
                        </h2>
                        <p className="text-sm text-text-secondary">
                            Członkostwa i przydziały pozostają zachowane.
                            Zarządzaj nimi w osobnej sekcji.
                        </p>
                        <Button asChild variant="secondary">
                            <Link
                                href={
                                    user.role === 'teacher'
                                        ? '/admin/assignments'
                                        : user.role === 'student'
                                          ? '/admin/classes'
                                          : showUrl
                                }
                            >
                                {user.role === 'teacher'
                                    ? 'Przydziały nauczycieli'
                                    : user.role === 'student'
                                      ? 'Klasy i członkostwa'
                                      : 'Szczegóły konta'}
                            </Link>
                        </Button>
                    </aside>
                </div>
                <section
                    aria-labelledby="reset-password"
                    className="space-y-4 border-t border-border pt-5"
                >
                    <h2 id="reset-password" className="text-lg font-semibold">
                        Reset hasła
                    </h2>
                    <p className="text-sm text-text-secondary">
                        Pozostaw puste, aby zachować obecne hasło. Nowe hasło
                        musi mieć 12–128 znaków.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {(['password', 'password_confirmation'] as const).map(
                            (key) => (
                                <FormField
                                    key={key}
                                    {...fields[key]}
                                    error={form.errors[key]}
                                >
                                    {(attributes) => (
                                        <PasswordInput
                                            {...attributes}
                                            autoComplete="new-password"
                                            maxLength={128}
                                            value={form.data[key]}
                                            onChange={(event) =>
                                                form.setData(
                                                    key,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            ),
                        )}
                    </div>
                </section>
                <div className="flex flex-col-reverse gap-2 border-t border-border pt-4 md:flex-row md:justify-end">
                    <Button
                        asChild
                        variant="secondary"
                        className="h-12 md:h-10"
                    >
                        <Link href={showUrl}>Anuluj</Link>
                    </Button>
                    <SubmitButton processing={form.processing}>
                        Zapisz zmiany
                    </SubmitButton>
                </div>
            </form>
        </>
    );
}
