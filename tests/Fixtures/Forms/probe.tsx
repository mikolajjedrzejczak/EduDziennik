import { useForm } from '@inertiajs/react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { Input } from '@/components/ui/input';

const fields = {
    email: { id: 'probe-email', label: 'Adres e-mail' },
    quantity: { id: 'probe-quantity', label: 'Liczba' },
    comment: { id: 'probe-comment', label: 'Komentarz' },
    password: { id: 'probe-password', label: 'Hasło' },
    password_confirmation: {
        id: 'probe-password-confirmation',
        label: 'Powtórz hasło',
    },
};

export default function Probe({ success = false }: { success?: boolean }) {
    // No remember key: passwords must never enter Inertia's remembered state.
    const form = useForm({
        email: '',
        quantity: '',
        comment: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <main className="mx-auto max-w-xl p-6">
            <h1 className="mb-6 text-xl font-semibold">Formularz techniczny</h1>
            {success && <p role="status">Formularz przyjęty.</p>}
            <form
                noValidate
                aria-busy={form.processing}
                className="grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    if (form.processing) return;
                    form.post('/__tests/forms/allowed', {
                        onFinish: () =>
                            form.reset('password', 'password_confirmation'),
                    });
                }}
            >
                <ErrorSummary errors={form.errors} fields={fields} />
                {Object.entries(fields).map(([key, field]) => {
                    const name = key as keyof typeof fields;
                    return (
                        <FormField
                            key={name}
                            {...field}
                            error={form.errors[name]}
                            hint={
                                name === 'quantity'
                                    ? 'Podaj liczbę od 1 do 5.'
                                    : undefined
                            }
                        >
                            {(attributes) => (
                                <Input
                                    {...attributes}
                                    name={name}
                                    required={name !== 'comment'}
                                    type={
                                        name.startsWith('password')
                                            ? 'password'
                                            : name === 'email'
                                              ? 'email'
                                              : 'text'
                                    }
                                    autoComplete={
                                        name.startsWith('password')
                                            ? 'new-password'
                                            : 'off'
                                    }
                                    inputMode={
                                        name === 'quantity'
                                            ? 'numeric'
                                            : undefined
                                    }
                                    value={form.data[name]}
                                    onChange={(event) =>
                                        form.setData(name, event.target.value)
                                    }
                                />
                            )}
                        </FormField>
                    );
                })}
                <SubmitButton processing={form.processing}>Zapisz</SubmitButton>
            </form>
        </main>
    );
}
