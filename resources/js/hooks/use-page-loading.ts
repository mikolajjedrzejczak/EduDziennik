import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function usePageLoading() {
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const off = [
            router.on('start', () => setLoading(true)),
            router.on('finish', () => setLoading(false)),
        ];

        return () => off.forEach((remove) => remove());
    }, []);

    return loading;
}
