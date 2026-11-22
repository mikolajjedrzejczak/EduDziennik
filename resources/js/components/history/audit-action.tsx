import { actionLabels } from '@/lib/history';
import type { AuditAction } from '@/types/history';

export function AuditActionBadge({ action }: { action: AuditAction }) {
    const tone =
        action === 'create'
            ? 'bg-success-bg text-success'
            : action === 'delete'
              ? 'bg-error-bg text-error'
              : 'bg-primary-subtle text-primary';
    return (
        <span
            className={`inline-block rounded px-2 py-1 text-xs font-medium ${tone}`}
        >
            {actionLabels[action]}
        </span>
    );
}
