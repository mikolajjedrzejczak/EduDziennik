import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useInitials } from '@/hooks/use-initials';
import { gradeHref } from '@/lib/grades';
import type { GradeListProps } from '@/types/grades';

/** Root/detail variants from 21:76 and 21:284; profile replaces the mock action. */
export function StudentGradesMobileHeader() {
    const { filters, options, auth } = usePage<GradeListProps>().props;
    const initials = useInitials();
    const subject = options.subjects.find(
        (item) => item.id === filters.subjectId,
    );

    return (
        <header className="flex min-h-14 shrink-0 items-center gap-1 border-b border-border bg-surface pr-2 pl-4">
            {filters.subjectId && (
                <Link
                    href={gradeHref(filters, { subject_id: null, page: null })}
                    preserveState
                    aria-label="Wróć do przedmiotów"
                    className="-ml-3 flex size-11 shrink-0 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:ring-focus"
                >
                    <ArrowLeft aria-hidden="true" className="size-[22px]" />
                </Link>
            )}
            <h1 className="min-w-0 flex-1 py-3 text-lg font-semibold wrap-anywhere">
                {subject?.label ?? 'Moje oceny'}
            </h1>
            {auth.user && (
                <Link
                    href="/settings/profile"
                    aria-label="Profil i hasło"
                    className="flex size-11 shrink-0 items-center justify-center rounded-md focus-visible:ring-[3px] focus-visible:ring-focus"
                >
                    <span className="flex size-7 items-center justify-center rounded-full bg-primary-muted text-xs font-medium text-primary-hover">
                        {initials(auth.user.name)}
                    </span>
                </Link>
            )}
        </header>
    );
}
