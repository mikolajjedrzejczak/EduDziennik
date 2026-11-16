import { useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';

/** Keep draft controls in sync with canonical URL props, including history visits. */
export function useFilterState<T>(
    initial: T,
): [T, Dispatch<SetStateAction<T>>] {
    const source = JSON.stringify(initial);
    const [state, setState] = useState({ source, value: initial });
    const value =
        state.source !== source && JSON.stringify(state.value) === state.source
            ? initial
            : state.value;
    if (state.source !== source) {
        // Keep edits made while a previous search was in flight. Clean controls
        // follow URL changes; an acknowledged draft already equals the new props.
        setState({ source, value });
    }
    const setValue: Dispatch<SetStateAction<T>> = (next) => {
        setState((previous) => ({
            ...previous,
            value:
                typeof next === 'function'
                    ? (next as (value: T) => T)(previous.value)
                    : next,
        }));
    };
    return [value, setValue];
}
