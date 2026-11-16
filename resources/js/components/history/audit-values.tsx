import { GradeChip } from '@/components/grades/grade-chip';
import { formatDate } from '@/lib/grades';
import { formatDateTime } from '@/lib/users';
import type { GradeSnapshot } from '@/types/history';
import type { Option } from '@/types/school';

export function AuditValue({ snapshot }: { snapshot: GradeSnapshot | null }) {
    if (!snapshot) return <span>—</span>;
    return (
        <span className="inline-flex flex-wrap items-center gap-2">
            <GradeChip
                value={snapshot.value}
                deleted={Boolean(snapshot.deleted_at)}
            />
            <span className="text-xs text-text-secondary">
                waga {snapshot.weight}
                {snapshot.deleted_at ? ' · usunięta' : ''}
            </span>
        </span>
    );
}

const fields = {
    value: 'Ocena',
    weight: 'Waga',
    category_id: 'Kategoria',
    topic: 'Temat',
    comment: 'Komentarz',
    graded_on: 'Data oceny',
    version: 'Wersja',
    deleted_at: 'Usunięto',
} as const;

export function SnapshotDetails({
    oldValues,
    newValues,
    categories,
}: {
    oldValues: GradeSnapshot | null;
    newValues: GradeSnapshot;
    categories: Option[];
}) {
    const value = (
        snapshot: GradeSnapshot | null,
        key: keyof typeof fields,
    ) => {
        const raw = snapshot?.[key];
        if (raw === null || raw === undefined || raw === '') return '—';
        if (key === 'category_id')
            return (
                categories.find((c) => c.id === raw)?.label ??
                `Kategoria #${raw}`
            );
        if (key === 'graded_on') return formatDate(String(raw));
        if (key === 'deleted_at') return formatDateTime(String(raw));
        return String(raw);
    };
    return (
        <details className="mt-2 text-xs">
            <summary className="min-h-11 cursor-pointer content-center rounded text-primary outline-none focus-visible:ring-[3px] focus-visible:ring-focus">
                Wartości przed i po
            </summary>
            <div className="grid grid-cols-[minmax(0,0.8fr)_repeat(2,minmax(0,1fr))] gap-2 border-t border-border pt-2">
                <span className="sr-only">Pole</span>
                <strong className="col-start-2">Przed</strong>
                <strong>Po</strong>
                {Object.entries(fields).map(([key, label]) => (
                    <div className="contents" key={key}>
                        <span className="text-text-secondary">{label}</span>
                        <span className="wrap-anywhere whitespace-pre-wrap">
                            {value(oldValues, key as keyof typeof fields)}
                        </span>
                        <span className="wrap-anywhere whitespace-pre-wrap">
                            {value(newValues, key as keyof typeof fields)}
                        </span>
                    </div>
                ))}
            </div>
        </details>
    );
}
