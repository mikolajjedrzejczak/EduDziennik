import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { GradeChip } from '@/components/grades/grade-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

describe('F-006 primitives', () => {
    it('preserves semantic type sizes alongside colors and still accepts size overrides', () => {
        expect(cn('text-label', 'text-text-primary')).toBe(
            'text-label text-text-primary',
        );
        expect(cn('text-grade', 'text-grade-5-fg')).toBe(
            'text-grade text-grade-5-fg',
        );
        expect(cn('text-label', 'text-lg')).toBe('text-lg');
    });
    it('uses keyboard focus and prevents repeated pending actions', async () => {
        const user = userEvent.setup();
        const action = vi.fn();
        const { rerender } = render(<Button onClick={action}>Zapisz</Button>);
        await user.tab();
        expect(screen.getByRole('button')).toHaveFocus();
        await user.keyboard('{Enter}');
        expect(action).toHaveBeenCalledTimes(1);
        expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
        rerender(
            <Button loading onClick={action}>
                Zapisz
            </Button>,
        );
        expect(screen.getByRole('button')).toBeDisabled();
        expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'true');
        await user.click(screen.getByRole('button'));
        expect(action).toHaveBeenCalledTimes(1);
    });

    it('keeps labels, hints and errors supplied by the form', async () => {
        const user = userEvent.setup();
        const { rerender } = render(
            <>
                <label htmlFor="email">E-mail</label>
                <Input
                    id="email"
                    placeholder="Adres"
                    aria-invalid
                    aria-describedby="error"
                />
                <p id="error">Nieprawidłowy adres.</p>
            </>,
        );
        await user.tab();
        expect(screen.getByLabelText('E-mail')).toHaveFocus();
        expect(screen.getByLabelText('E-mail')).toHaveAccessibleDescription(
            'Nieprawidłowy adres.',
        );
        rerender(<Input aria-label="E-mail" disabled />);
        await user.type(screen.getByRole('textbox'), 'test');
        expect(screen.getByRole('textbox')).toHaveValue('');
    });

    it('renders all grades with readable values, deletion and override descriptions', () => {
        render(
            <>
                {([1, 2, 3, 4, 5, 6] as const).map((value) => (
                    <GradeChip key={value} value={value} />
                ))}
                <GradeChip value={2} deleted differsFromAssessment />
            </>,
        );
        for (const value of [1, 2, 3, 4, 5, 6])
            expect(
                screen.getByText(`Ocena ${value}`, { exact: true }),
            ).toBeInTheDocument();
        expect(
            screen.getByText('Ocena 2, usunięta, indywidualne parametry oceny'),
        ).toBeInTheDocument();
    });

    it('uses links and buttons with keyboard activation for interactive chips', async () => {
        const user = userEvent.setup();
        const action = vi.fn();
        render(
            <>
                <GradeChip value={4} href="/grades/4" />
                <GradeChip value={5} onClick={action} />
                <GradeChip value={6} onClick={action} disabled />
            </>,
        );
        await user.tab();
        expect(screen.getByRole('link', { name: 'Ocena 4' })).toHaveFocus();
        await user.tab();
        expect(screen.getByRole('button', { name: 'Ocena 5' })).toHaveFocus();
        await user.keyboard(' ');
        expect(action).toHaveBeenCalledOnce();
        expect(screen.getByRole('button', { name: 'Ocena 6' })).toBeDisabled();
    });
});
