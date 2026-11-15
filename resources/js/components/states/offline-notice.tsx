import { TriangleAlert } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/use-online-status';

/**
 * Live region stays mounted so assistive tech announces the change;
 * it is visually absent while the connection is available.
 */
export function OfflineNotice() {
    const online = useOnlineStatus();

    return (
        <div role="status" aria-live="polite">
            {!online && (
                <div className="flex items-start gap-2 bg-warning-bg px-4 py-3 text-sm text-warning">
                    <TriangleAlert
                        aria-hidden="true"
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span>
                        Brak połączenia. Zapisy są wstrzymane — nic nie zostanie
                        wysłane ponownie automatycznie. Po powrocie sieci
                        sprawdź dane przed ponownym zapisem.
                    </span>
                </div>
            )}
        </div>
    );
}
