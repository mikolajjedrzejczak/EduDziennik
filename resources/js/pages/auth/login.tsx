import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/login';

const noErrors: Record<string, string> = {};

const fields = {
    email: { id: 'email', label: 'Adres e-mail' },
    password: { id: 'password', label: 'Hasło' },
};

export type LoginProps = {
    errors?: Record<string, string>;
    retryAfter?: number | null;
};

export default function Login({ errors = noErrors, retryAfter }: LoginProps) {
    // No remember key: passwords must not be persisted in history state.
    const form = useForm({ email: '', password: '' });
    const feedback = useMemo(
        () => ({ ...errors, ...form.errors }),
        [errors, form.errors],
    );

    return (
        <>
            <Head title="Logowanie" />
            <form
                noValidate
                aria-busy={form.processing}
                className="flex flex-1 flex-col gap-5 md:flex-none"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing) return;
                    form.post(store.url(), {
                        onFinish: () => form.reset('password'),
                    });
                }}
            >
                <header className="space-y-1.5">
                    <h1 className="text-[32px] leading-10 font-semibold md:text-2xl md:leading-8">
                        Zaloguj się
                    </h1>
                    <p className="text-base leading-6 text-form-muted md:text-sm md:leading-5">
                        Użyj szkolnego adresu e-mail.
                        <span className="hidden md:inline">
                            {' '}
                            Po zalogowaniu trafisz do pulpitu swojej roli.
                        </span>
                    </p>
                </header>
                <ErrorSummary
                    errors={feedback}
                    fields={fields}
                    title="Nie udało się zalogować"
                />
                {retryAfter != null && (
                    <p className="sr-only">Czas oczekiwania: {retryAfter} s.</p>
                )}
                <FormField {...fields.email} error={feedback.email}>
                    {(attributes) => (
                        <Input
                            {...attributes}
                            name="email"
                            type="email"
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            autoFocus
                            required
                            maxLength={254}
                            placeholder="np. jan.kowalski@lo4.edu.pl"
                            value={form.data.email}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    )}
                </FormField>
                <FormField {...fields.password} error={feedback.password}>
                    {(attributes) => (
                        <PasswordInput
                            {...attributes}
                            name="password"
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            disabled={form.processing}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                    )}
                </FormField>
                <div className="flex-1 md:hidden" />
                <SubmitButton
                    processing={form.processing}
                    className="login-submit w-full md:h-11"
                    data-test="login-button"
                >
                    {form.processing ? 'Logowanie…' : 'Zaloguj się'}
                </SubmitButton>
                <p className="text-center text-[13px] leading-[18px] text-form-muted md:text-left">
                    <span className="hidden md:inline">Nie masz konta? </span>
                    Konta zakłada administrator szkoły
                    <span className="hidden md:inline">
                        {' '}
                        — zgłoś się do sekretariatu
                    </span>
                    .
                </p>
            </form>
        </>
    );
}
