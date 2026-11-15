import type { AccountStatus, Role, UserDTO } from '@/types/users';

export const roleLabels: Record<Role, string> = {
    student: 'Uczeń',
    teacher: 'Nauczyciel',
    admin: 'Administrator',
};

export function statusLabel(user: Pick<UserDTO, 'status' | 'deletedAt'>) {
    if (user.deletedAt) return 'Usunięte';
    const labels: Record<AccountStatus, string> = {
        active: 'Aktywne',
        inactive: 'Nieaktywne',
    };
    return labels[user.status];
}

export function fullName(user: Pick<UserDTO, 'firstName' | 'lastName'>) {
    return `${user.lastName} ${user.firstName}`.trim();
}

export function initials(user: Pick<UserDTO, 'firstName' | 'lastName'>) {
    return (
        `${[...user.firstName][0] ?? ''}${[...user.lastName][0] ?? ''}`.toUpperCase() ||
        '?'
    );
}

/** Dates arrive as ISO-8601 UTC; shown in the school's time zone. */
export function formatDateTime(value: string | null) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('pl-PL', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'Europe/Warsaw',
    }).format(new Date(value));
}
