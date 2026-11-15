import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { Input } from '@/components/ui/input';

describe('shared form feedback', () => {
    it('preserves thirty errors with identical messages and does not link inherited object keys', () => {
        const errors = Object.fromEntries(
            Array.from({ length: 30 }, (_, i) => [
                `items.${i}.name`,
                'Uzupełnij pole.',
            ]),
        );
        render(
            <ErrorSummary
                errors={{ ...errors, toString: 'Błąd ogólny.' }}
                fields={{}}
            />,
        );
        expect(screen.getAllByRole('listitem')).toHaveLength(31);
        expect(screen.getAllByText('Uzupełnij pole.')).toHaveLength(30);
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
    it('links the visible label, hint and error to the input; summary focuses and links to the field', async () => {
        const user = userEvent.setup();
        const message = 'Podaj prawidłowy adres e-mail.';
        const view = render(
            <>
                <ErrorSummary
                    errors={{}}
                    fields={{ email: { id: 'email', label: 'Adres e-mail' } }}
                />
                <FormField
                    id="email"
                    label="Adres e-mail"
                    hint="Adres kontaktowy"
                    error={message}
                >
                    {(attributes) => (
                        <Input
                            {...attributes}
                            defaultValue="niesekretna treść"
                        />
                    )}
                </FormField>
            </>,
        );
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        const input = screen.getByRole('textbox', { name: 'Adres e-mail' });
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAccessibleDescription(
            `Adres kontaktowy ${message}`,
        );
        view.rerender(
            <>
                <ErrorSummary
                    errors={{ email: message }}
                    fields={{ email: { id: 'email', label: 'Adres e-mail' } }}
                />
                <FormField
                    id="email"
                    label="Adres e-mail"
                    hint="Adres kontaktowy"
                    error={message}
                >
                    {(attributes) => (
                        <Input
                            {...attributes}
                            defaultValue="niesekretna treść"
                        />
                    )}
                </FormField>
            </>,
        );
        expect(screen.getByRole('alert')).toHaveFocus();
        await user.tab();
        expect(screen.getByRole('link')).toHaveFocus();
        await user.keyboard('{Enter}');
        expect(input).toHaveFocus();
        expect(input).toHaveValue('niesekretna treść');
        expect(screen.getAllByText(message, { exact: false })).toHaveLength(2);
    });

    it('keeps form/conflict and unknown field errors visible without broken links or HTML execution', () => {
        render(
            <ErrorSummary
                errors={{
                    form: 'Nie zapisano.',
                    conflict: 'Odśwież dane.',
                    'items.0.name': '<script>alert(1)</script>',
                }}
                fields={{}}
            />,
        );
        expect(screen.getByRole('alert')).toHaveFocus();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(
            screen.getByText('<script>alert(1)</script>'),
        ).toBeInTheDocument();
    });

    it('does not add invalid/description attributes for an empty field state', () => {
        render(
            <FormField id="empty" label="Komentarz">
                {(attributes) => <Input {...attributes} />}
            </FormField>,
        );
        expect(screen.getByRole('textbox')).not.toHaveAttribute('aria-invalid');
        expect(screen.getByRole('textbox')).not.toHaveAttribute(
            'aria-describedby',
        );
    });

    it('announces pending and prevents submit while preserving explicit disabled state', () => {
        const view = render(<SubmitButton processing>Zapisz</SubmitButton>);
        expect(screen.getByRole('button', { name: 'Zapisz' })).toBeDisabled();
        expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'true');
        view.rerender(
            <SubmitButton processing={false} disabled>
                Zapisz
            </SubmitButton>,
        );
        expect(screen.getByRole('button')).toBeDisabled();
        view.rerender(<SubmitButton processing={false}>Zapisz</SubmitButton>);
        expect(screen.getByRole('button')).toBeEnabled();
        expect(screen.getByRole('button')).toHaveAttribute('type', 'submit');
    });
});
