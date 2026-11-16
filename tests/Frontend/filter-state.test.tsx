import { act, renderHook } from '@testing-library/react';
import { expect, it } from 'vitest';
import { useFilterState } from '@/hooks/use-filter-state';

it('follows canonical history filters without discarding newer typing during a response', () => {
    const { result, rerender } = renderHook(({ q }) => useFilterState(q), {
        initialProps: { q: '' },
    });
    act(() => result.current[1]('Nowak'));
    rerender({ q: 'Nowak' });
    act(() => result.current[1]('Nowakowski'));
    rerender({ q: 'Nowakow' });
    expect(result.current[0]).toBe('Nowakowski');
    rerender({ q: 'Nowakowski' });
    rerender({ q: 'Nowak' });
    expect(result.current[0]).toBe('Nowak');
    rerender({ q: 'Nowakowski' });
    expect(result.current[0]).toBe('Nowakowski');
});
