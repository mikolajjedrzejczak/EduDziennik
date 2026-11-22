import type { AuditAction } from '@/types/history';

/** Polish labels of grade audit operations, shared by history lists and filters. */
export const actionLabels: Record<AuditAction, string> = {
    create: 'Dodanie',
    update: 'Zmiana',
    delete: 'Usunięcie',
};
