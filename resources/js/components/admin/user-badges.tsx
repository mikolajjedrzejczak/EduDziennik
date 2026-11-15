import { roleLabels, statusLabel } from '@/lib/users';
import type { UserDTO } from '@/types/users';

export function RoleBadge({ role }: { role: UserDTO['role'] }) {
    return (
        <span className="inline-flex rounded-sm bg-surface-secondary px-2 py-0.5 text-xs font-medium text-text-secondary">
            {roleLabels[role]}
        </span>
    );
}

/** Status is always text, never colour alone. */
export function StatusBadge({
    user,
}: {
    user: Pick<UserDTO, 'status' | 'deletedAt'>;
}) {
    const active = user.status === 'active' && !user.deletedAt;
    return (
        <span
            className={`inline-flex rounded-sm px-2 py-0.5 text-xs font-medium ${active ? 'bg-success-bg text-success' : 'bg-surface-secondary text-text-secondary'}`}
        >
            {statusLabel(user)}
        </span>
    );
}
