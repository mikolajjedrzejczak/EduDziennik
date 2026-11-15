import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { EmptyState } from '@/components/states/empty-state';
import { ErrorState } from '@/components/states/error-state';
import { LoadingState } from '@/components/states/loading-state';
import { OfflineNotice } from '@/components/states/offline-notice';
import { SuccessNotice } from '@/components/states/success-notice';

describe('F-404 shared states', () => {
    it('empty state explains the absence and offers an explicit action', async () => {
        const user = userEvent.setup();
        const clear = vi.fn();
        render(
            <EmptyState
                variant="no-results"
                title="Brak wyników"
                description="Zmień filtry."
                action={{ label: 'Wyczyść filtry', onClick: clear }}
            />,
        );
        expect(
            screen.getByRole('heading', { name: 'Brak wyników' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Wyczyść filtry' }),
        );
        expect(clear).toHaveBeenCalledTimes(1);
    });

    it('loading state is a busy status region with a text label', () => {
        render(<LoadingState label="Ładowanie dziennika" />);
        const region = screen.getByRole('status', {
            name: 'Ładowanie dziennika',
        });
        expect(region).toHaveAttribute('aria-busy', 'true');
    });

    it('error state is an alert that takes focus and retries only when allowed', async () => {
        const user = userEvent.setup();
        const retry = vi.fn();
        const { rerender } = render(
            <ErrorState
                title="Nie udało się wczytać"
                description="Spróbuj ponownie."
                onRetry={retry}
                correlationId="abc-123"
            />,
        );
        const alert = screen.getByRole('alert');
        expect(alert).toHaveFocus();
        expect(alert).toHaveTextContent('abc-123');
        await user.click(
            screen.getByRole('button', { name: 'Spróbuj ponownie' }),
        );
        expect(retry).toHaveBeenCalledTimes(1);
        rerender(<ErrorState title="Błąd" description="Opis" />);
        expect(
            screen.queryByRole('button', { name: 'Spróbuj ponownie' }),
        ).not.toBeInTheDocument();
    });

    it('error state does not retry twice while a retry is pending', async () => {
        const user = userEvent.setup();
        const retry = vi.fn();
        render(
            <ErrorState
                title="Błąd"
                description="Opis"
                onRetry={retry}
                retrying
            />,
        );
        const button = screen.getByRole('button', { name: 'Spróbuj ponownie' });
        expect(button).toBeDisabled();
        await user.click(button);
        expect(retry).not.toHaveBeenCalled();
    });

    it('success notice is a polite status, not an alert', () => {
        render(<SuccessNotice>Zapisano ocenę.</SuccessNotice>);
        expect(screen.getByRole('status')).toHaveTextContent('Zapisano ocenę.');
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('offline notice follows connectivity events through a live region', () => {
        render(<OfflineNotice />);
        expect(screen.queryByText(/Brak połączenia/)).not.toBeInTheDocument();
        const online = vi.spyOn(navigator, 'onLine', 'get');
        online.mockReturnValue(false);
        act(() => {
            window.dispatchEvent(new Event('offline'));
        });
        expect(screen.getByRole('status')).toHaveTextContent('Brak połączenia');
        online.mockReturnValue(true);
        act(() => {
            window.dispatchEvent(new Event('online'));
        });
        expect(screen.queryByText(/Brak połączenia/)).not.toBeInTheDocument();
    });
});
