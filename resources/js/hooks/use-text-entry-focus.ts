import { useEffect, useState } from 'react';

const ENTRY = 'input:not([type=checkbox]):not([type=radio]), textarea, select';

/**
 * True while a text field has focus. On 390px the on-screen keyboard covers a
 * fixed bottom bar, so the bar steps aside instead of hiding the field.
 */
export function useTextEntryFocus(): boolean {
    const [active, setActive] = useState(false);

    useEffect(() => {
        const sync = () =>
            setActive(document.activeElement?.matches(ENTRY) ?? false);
        const afterBlur = () => queueMicrotask(sync);
        document.addEventListener('focusin', sync);
        document.addEventListener('focusout', afterBlur);

        return () => {
            document.removeEventListener('focusin', sync);
            document.removeEventListener('focusout', afterBlur);
        };
    }, []);

    return active;
}
