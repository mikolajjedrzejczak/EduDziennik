import { useSyncExternalStore } from 'react';

export type Viewport = 'mobile' | 'tablet' | 'desktop';

// docs/design/RESPONSIVENESS.md: <768 mobile, 768-1279 tablet, >=1280 desktop.
const TABLET_MIN = 768;
const DESKTOP_MIN = 1280;

function subscribe(callback: () => void) {
    const queries = [
        window.matchMedia(`(min-width: ${TABLET_MIN}px)`),
        window.matchMedia(`(min-width: ${DESKTOP_MIN}px)`),
    ];
    queries.forEach((query) => query.addEventListener('change', callback));

    return () =>
        queries.forEach((query) =>
            query.removeEventListener('change', callback),
        );
}

function snapshot(): Viewport {
    if (window.matchMedia(`(min-width: ${DESKTOP_MIN}px)`).matches) {
        return 'desktop';
    }

    return window.matchMedia(`(min-width: ${TABLET_MIN}px)`).matches
        ? 'tablet'
        : 'mobile';
}

/** Exactly one navigation is mounted at a time, so the DOM is never duplicated. */
export function useViewport(): Viewport {
    return useSyncExternalStore(subscribe, snapshot, () => 'desktop');
}
