import { fireEvent, render, screen, act } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { SearchFilterForm } from '@/components/forms/search-filter-form';

const before = vi.hoisted(() => ({ cancel: () => {} }));
vi.mock('@inertiajs/react', () => ({
    router: {
        on: (_: string, callback: () => void) => {
            before.cancel = callback;
            return vi.fn();
        },
    },
}));
function setup() {
    const submit = vi.fn((event: React.SubmitEvent<HTMLFormElement>) =>
        event.preventDefault(),
    );
    const view = render(
        <SearchFilterForm onSubmit={submit}>
            <input type="search" aria-label="Szukaj" />
            <button type="submit">Filtruj</button>
        </SearchFilterForm>,
    );
    return { submit, view, input: screen.getByRole('searchbox') };
}
describe('shared debounced search', () => {
    it('submits only the latest query after 350ms of inactivity', () => {
        vi.useFakeTimers();
        const { input, submit } = setup();
        fireEvent.change(input, { target: { value: 'Żó' } });
        act(() => vi.advanceTimersByTime(300));
        fireEvent.change(input, { target: { value: 'Żółć%_' } });
        act(() => vi.advanceTimersByTime(349));
        expect(submit).not.toHaveBeenCalled();
        act(() => vi.advanceTimersByTime(1));
        expect(submit).toHaveBeenCalledTimes(1);
        expect(input).toHaveValue('Żółć%_');
    });
    it('manual submit cancels the queued submission', () => {
        vi.useFakeTimers();
        const { input, submit } = setup();
        fireEvent.change(input, { target: { value: 'Nowak' } });
        fireEvent.click(screen.getByRole('button'));
        act(() => vi.advanceTimersByTime(500));
        expect(submit).toHaveBeenCalledTimes(1);
    });
    it('cancels on navigation and unmount, and waits for composition', () => {
        vi.useFakeTimers();
        const { input, submit, view } = setup();
        fireEvent.compositionStart(input);
        fireEvent.change(input, { target: { value: 'Ł' } });
        act(() => vi.advanceTimersByTime(500));
        expect(submit).not.toHaveBeenCalled();
        fireEvent.compositionEnd(input);
        act(() => vi.advanceTimersByTime(350));
        expect(submit).toHaveBeenCalledTimes(1);
        fireEvent.change(input, { target: { value: 'Łódź' } });
        before.cancel();
        act(() => vi.advanceTimersByTime(500));
        expect(submit).toHaveBeenCalledTimes(1);
        fireEvent.change(input, { target: { value: '' } });
        view.unmount();
        act(() => vi.advanceTimersByTime(500));
        expect(submit).toHaveBeenCalledTimes(1);
    });
});
