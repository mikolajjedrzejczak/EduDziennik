import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { DeleteUserDialog } from '@/components/admin/delete-user-dialog';
import { RoleBadge, StatusBadge } from '@/components/admin/user-badges';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/states/empty-state';
import { formatDateTime, fullName } from '@/lib/users';
import type { UserDetailProps } from '@/types/users';

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-text-secondary">{label}</dt>
            <dd className="text-sm wrap-anywhere">{children}</dd>
        </div>
    );
}

export default function ShowUser({
    user,
    enrollments,
    assignments,
    relationTotals,
}: UserDetailProps) {
    const name = fullName(user);
    return (
        <>
            <Head title={name} />
            <div className="space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <Link
                    href="/admin/users"
                    className="inline-flex min-h-11 items-center gap-1 text-sm text-primary underline outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
                >
                    <ArrowLeft className="size-4" aria-hidden="true" />
                    Użytkownicy
                </Link>
                <header className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl leading-8 font-semibold wrap-anywhere">
                        {name}
                    </h1>
                    <RoleBadge role={user.role} />
                    <StatusBadge user={user} />
                    {!user.deletedAt && (
                        <Button asChild variant="secondary">
                            <Link href={`/admin/users/${user.id}/edit`}>
                                Edytuj użytkownika
                            </Link>
                        </Button>
                    )}
                    <DeleteUserDialog user={user} />
                </header>
                {user.deletedAt && (
                    <p
                        role="status"
                        className="rounded-md border border-border bg-surface-secondary p-3 text-sm"
                    >
                        Konto zostało usunięte {formatDateTime(user.deletedAt)}.
                        Historia relacji pozostaje dostępna.
                    </p>
                )}
                <section
                    aria-labelledby="user-data"
                    className="rounded-lg border border-border bg-surface p-4 md:p-6"
                >
                    <h2 id="user-data" className="mb-4 text-lg font-semibold">
                        Dane konta
                    </h2>
                    <dl className="grid gap-4 md:grid-cols-2">
                        <Field label="Imię">{user.firstName}</Field>
                        <Field label="Nazwisko">{user.lastName}</Field>
                        <Field label="E-mail">{user.email}</Field>
                        <Field label="Ostatnie logowanie">
                            {formatDateTime(user.lastLoginAt)}
                        </Field>
                    </dl>
                </section>
                {user.role === 'student' && (
                    <section
                        aria-labelledby="user-enrollments"
                        className="rounded-lg border border-border bg-surface p-4 md:p-6"
                    >
                        <h2
                            id="user-enrollments"
                            className="mb-4 text-lg font-semibold"
                        >
                            Klasy ucznia
                        </h2>
                        {enrollments.length ? (
                            <ul className="divide-y divide-border rounded-md border border-border">
                                {enrollments.map((enrollment) => (
                                    <li
                                        key={enrollment.id}
                                        className="space-y-1 p-3 text-sm"
                                    >
                                        <p className="font-medium wrap-anywhere">
                                            {enrollment.schoolClass.label}
                                        </p>
                                        <p className="text-xs text-text-secondary">
                                            {enrollment.startsOn} —{' '}
                                            {enrollment.endsOn ??
                                                'bez daty zakończenia'}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                title="Brak klas"
                                description="Uczeń nie ma zapisów do klas."
                            />
                        )}
                        <Truncation
                            shown={enrollments.length}
                            total={relationTotals.enrollments}
                        />
                    </section>
                )}
                {user.role === 'teacher' && (
                    <section
                        aria-labelledby="user-assignments"
                        className="rounded-lg border border-border bg-surface p-4 md:p-6"
                    >
                        <h2
                            id="user-assignments"
                            className="mb-4 text-lg font-semibold"
                        >
                            Przydziały nauczyciela
                        </h2>
                        {assignments.length ? (
                            <ul className="divide-y divide-border rounded-md border border-border">
                                {assignments.map((assignment) => (
                                    <li
                                        key={assignment.id}
                                        className="space-y-1 p-3 text-sm"
                                    >
                                        <p className="font-medium wrap-anywhere">
                                            {assignment.subject.label} ·{' '}
                                            {assignment.schoolClass.label}
                                        </p>
                                        <p className="text-xs text-text-secondary">
                                            {assignment.term.label}
                                            {assignment.revokedAt
                                                ? ` · Cofnięty ${formatDateTime(assignment.revokedAt)}`
                                                : ' · Aktywny'}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <EmptyState
                                title="Brak przydziałów"
                                description="Nauczyciel nie ma przydziałów."
                            />
                        )}
                        <Truncation
                            shown={assignments.length}
                            total={relationTotals.assignments}
                        />
                    </section>
                )}
            </div>
        </>
    );
}

function Truncation({ shown, total }: { shown: number; total: number }) {
    if (total <= shown) return null;
    return (
        <p role="status" className="mt-3 text-xs text-text-secondary">
            Wyświetlono {shown} z {total} rekordów.
        </p>
    );
}
