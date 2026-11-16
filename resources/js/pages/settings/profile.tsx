import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { LogoutButton } from '@/components/navigation/app-sidebar';
import { SuccessNotice } from '@/components/states/success-notice';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import type { User } from '@/types';

type ProfileProps = { user: User; passwordRateLimited?: boolean };
const nameFields = {
    first_name: { id: 'first-name', label: 'Imię' },
    last_name: { id: 'last-name', label: 'Nazwisko' },
};
const passwordFields = {
    current_password: { id: 'current-password', label: 'Obecne hasło' },
    password: { id: 'new-password', label: 'Nowe hasło' },
    password_confirmation: {
        id: 'confirm-password',
        label: 'Powtórz nowe hasło',
    },
};
const roles = {
    student: 'Uczeń',
    teacher: 'Nauczyciel',
    admin: 'Administrator',
};

export default function Profile({
    user,
    passwordRateLimited = false,
}: ProfileProps) {
    const { flash } = usePage().props;
    const profile = useForm({
        first_name: user.firstName,
        last_name: user.lastName,
    });
    // Deliberately no remember key: passwords must never enter Inertia history state.
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const { reset } = password;
    useEffect(() => {
        const clear = () => reset();
        window.addEventListener('pagehide', clear);
        window.addEventListener('pageshow', clear);
        return () => {
            window.removeEventListener('pagehide', clear);
            window.removeEventListener('pageshow', clear);
        };
    }, [reset]);

    return (
        <div className="space-y-6 p-4 md:p-6 xl:px-10 xl:py-7">
            <Head title="Profil i hasło" />
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Profil i hasło
                </h1>
                <p className="text-sm text-text-secondary">
                    Zmień imię, nazwisko lub hasło. Adres e-mail zmienia
                    administrator szkoły.
                </p>
            </header>
            {flash?.success && <SuccessNotice>{flash.success}</SuccessNotice>}
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,480px)_minmax(0,1fr)]">
                <section
                    aria-labelledby="profile-heading"
                    className="min-w-0 rounded-lg border border-border bg-surface"
                >
                    <div className="flex items-center gap-4 border-b border-border p-4 md:p-6">
                        <div
                            aria-hidden="true"
                            className="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary-muted text-lg font-semibold text-primary-hover"
                        >
                            {user.firstName.charAt(0)}
                            {user.lastName.charAt(0)}
                        </div>
                        <div className="min-w-0">
                            <h2
                                id="profile-heading"
                                className="text-lg font-semibold wrap-anywhere"
                            >
                                {user.name}
                            </h2>
                            <p className="text-sm text-text-secondary">
                                {roles[user.role]}
                            </p>
                        </div>
                    </div>
                    <form
                        noValidate
                        aria-label="Dane profilu"
                        aria-busy={profile.processing}
                        className="space-y-4 p-4 md:p-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!profile.processing)
                                profile.patch('/profile', {
                                    preserveScroll: true,
                                });
                        }}
                    >
                        <ErrorSummary
                            errors={profile.errors}
                            fields={nameFields}
                        />
                        {(['first_name', 'last_name'] as const).map((field) => (
                            <FormField
                                key={field}
                                {...nameFields[field]}
                                error={profile.errors[field]}
                            >
                                {(attributes) => (
                                    <Input
                                        {...attributes}
                                        value={profile.data[field]}
                                        autoComplete={
                                            field === 'first_name'
                                                ? 'given-name'
                                                : 'family-name'
                                        }
                                        maxLength={80}
                                        required
                                        disabled={profile.processing}
                                        onChange={(event) =>
                                            profile.setData(
                                                field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        ))}
                        <FormField
                            id="email"
                            label="E-mail"
                            hint="Adres e-mail może zmienić administrator szkoły."
                        >
                            {(attributes) => (
                                <Input
                                    {...attributes}
                                    type="email"
                                    value={user.email}
                                    readOnly
                                    autoComplete="username"
                                />
                            )}
                        </FormField>
                        <SubmitButton processing={profile.processing}>
                            Zapisz dane
                        </SubmitButton>
                    </form>
                </section>
                <section
                    aria-labelledby="password-heading"
                    className="min-w-0 space-y-4 rounded-lg border border-border bg-surface p-4 md:p-6"
                >
                    <h2 id="password-heading" className="text-lg font-semibold">
                        Zmiana hasła
                    </h2>
                    <p className="text-sm text-text-secondary">
                        Zmiana hasła wyloguje pozostałe sesje. Bieżąca sesja
                        pozostanie aktywna.
                    </p>
                    <form
                        noValidate
                        aria-label="Zmiana hasła"
                        aria-busy={password.processing}
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (!password.processing)
                                password.put('/profile/password', {
                                    preserveScroll: true,
                                    onFinish: () => password.reset(),
                                });
                        }}
                    >
                        <ErrorSummary
                            errors={
                                passwordRateLimited
                                    ? {
                                          form: 'Zbyt wiele prób zmiany hasła. Spróbuj ponownie za minutę.',
                                      }
                                    : password.errors
                            }
                            fields={passwordFields}
                        />
                        {(
                            [
                                'current_password',
                                'password',
                                'password_confirmation',
                            ] as const
                        ).map((field) => (
                            <FormField
                                key={field}
                                {...passwordFields[field]}
                                error={password.errors[field]}
                                hint={
                                    field === 'password'
                                        ? '12–128 znaków. Nowe hasło musi być inne niż obecne.'
                                        : undefined
                                }
                            >
                                {(attributes) => (
                                    <PasswordInput
                                        {...attributes}
                                        value={password.data[field]}
                                        autoComplete={
                                            field === 'current_password'
                                                ? 'current-password'
                                                : 'new-password'
                                        }
                                        required
                                        disabled={password.processing}
                                        onChange={(event) =>
                                            password.setData(
                                                field,
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        ))}
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <LogoutButton className="text-text-secondary" />
                            <SubmitButton processing={password.processing}>
                                Zmień hasło
                            </SubmitButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    );
}
