import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, Eye, ListFilter, Search } from 'lucide-react';
import { useId, useState } from 'react';
import { RoleBadge, StatusBadge } from '@/components/admin/user-badges';
import { ErrorSummary } from '@/components/forms/error-summary';
import { OptionField } from '@/components/school/option-field';
import { Pagination } from '@/components/school/pagination';
import { EmptyState } from '@/components/states/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, fullName, initials, roleLabels } from '@/lib/users';
import type { UserFilters, UserListProps, UserSort } from '@/types/users';

const statusOptions = [
    { id: 'active', label: 'Aktywne' },
    { id: 'inactive', label: 'Nieaktywne' },
    { id: 'deleted', label: 'Usunięte' },
];

function params(filters: UserFilters, override: Record<string, unknown> = {}) {
    const query: Record<string, unknown> = {
        q: filters.q || undefined,
        role: filters.role ?? undefined,
        status: filters.status ?? undefined,
        school_class_id: filters.schoolClassId ?? undefined,
        term_id: filters.termId ?? undefined,
        per_page: filters.perPage === 10 ? undefined : filters.perPage,
        sort: filters.sort === 'last_name' ? undefined : filters.sort,
        direction: filters.direction === 'asc' ? undefined : filters.direction,
        ...override,
    };
    return Object.fromEntries(
        Object.entries(query).filter(([, value]) => value !== undefined),
    );
}

function href(filters: UserFilters, override: Record<string, unknown> = {}) {
    const query = new URLSearchParams(
        Object.entries(params(filters, override)).map(([key, value]) => [
            key,
            String(value),
        ]),
    ).toString();
    return `/admin/users${query ? `?${query}` : ''}`;
}

export default function Users({ users, filters, options }: UserListProps) {
    const { errors } = usePage().props;
    const [pending, setPending] = useState(false);
    const activeFilters = [
        filters.role,
        filters.status,
        filters.schoolClassId,
    ].filter(Boolean).length;
    const hasFilters = Boolean(filters.q) || activeFilters > 0;

    function sortLink(sort: UserSort, label: string) {
        const current = filters.sort === sort;
        const next = current && filters.direction === 'asc' ? 'desc' : 'asc';
        return (
            <Link
                href={href(filters, {
                    sort:
                        sort === 'last_name' && next === 'asc'
                            ? undefined
                            : sort,
                    direction: next === 'asc' ? undefined : next,
                    page: undefined,
                })}
                className="inline-flex min-h-11 items-center gap-1 font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-focus"
            >
                {label}
                {current && (
                    <span aria-hidden="true">
                        {filters.direction === 'asc' ? '↑' : '↓'}
                    </span>
                )}
            </Link>
        );
    }
    function ariaSort(sort: UserSort) {
        if (filters.sort !== sort) return 'none';
        return filters.direction === 'asc' ? 'ascending' : 'descending';
    }

    return (
        <>
            <Head title="Użytkownicy" />
            <div className="space-y-5 p-4 md:p-6 xl:px-10 xl:py-7">
                <header>
                    <h1 className="text-2xl leading-8 font-semibold">
                        Użytkownicy
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        {users.meta.total} kont: uczniowie, nauczyciele i
                        administratorzy.
                    </p>
                </header>
                <ErrorSummary
                    errors={errors}
                    fields={{}}
                    title="Sprawdź wybrane filtry"
                />
                <UserFilterForm
                    key={JSON.stringify(params(filters))}
                    filters={filters}
                    options={options}
                    activeFilters={activeFilters}
                    onPending={setPending}
                />
                {hasFilters && (
                    <p className="flex flex-wrap items-center gap-3 text-sm text-text-secondary">
                        <span>Wyników: {users.meta.total}</span>
                        <Link
                            href="/admin/users"
                            className="inline-flex min-h-11 items-center text-primary underline"
                        >
                            Wyczyść filtry
                        </Link>
                    </p>
                )}
                <section
                    aria-label="Lista użytkowników"
                    aria-busy={pending}
                    className="min-w-0"
                >
                    {users.data.length === 0 ? (
                        <EmptyState
                            variant={hasFilters ? 'no-results' : 'no-data'}
                            title={hasFilters ? 'Brak wyników' : 'Brak kont'}
                            description={
                                hasFilters
                                    ? 'Zmień lub wyczyść filtry.'
                                    : 'W systemie nie ma jeszcze kont.'
                            }
                        />
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto rounded-lg border border-border bg-surface md:block">
                                <table className="w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Użytkownicy, strona{' '}
                                        {users.meta.currentPage}
                                    </caption>
                                    <thead className="bg-surface-secondary text-xs text-text-secondary">
                                        <tr>
                                            <th
                                                scope="col"
                                                aria-sort={ariaSort(
                                                    'last_name',
                                                )}
                                                className="px-4"
                                            >
                                                {sortLink(
                                                    'last_name',
                                                    'Nazwisko i imię',
                                                )}
                                            </th>
                                            <th
                                                scope="col"
                                                aria-sort={ariaSort('email')}
                                                className="px-3"
                                            >
                                                {sortLink('email', 'E-mail')}
                                            </th>
                                            <th
                                                scope="col"
                                                aria-sort={ariaSort('role')}
                                                className="px-3"
                                            >
                                                {sortLink('role', 'Rola')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="hidden px-3 xl:table-cell"
                                            >
                                                Klasa / przydział
                                            </th>
                                            <th scope="col" className="px-3">
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                className="hidden px-3 xl:table-cell"
                                            >
                                                Ostatnie logowanie
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 text-right"
                                            >
                                                <span className="sr-only">
                                                    Akcje
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {users.data.map((user) => (
                                            <tr
                                                key={user.id}
                                                className="border-t border-border"
                                            >
                                                <td className="max-w-56 px-4 py-2 font-medium wrap-anywhere">
                                                    <span className="flex items-center gap-2">
                                                        <Avatar
                                                            text={initials(
                                                                user,
                                                            )}
                                                        />
                                                        <span className="min-w-0">
                                                            {fullName(user)}
                                                        </span>
                                                    </span>
                                                </td>
                                                <td className="max-w-56 px-3 py-2 wrap-anywhere text-text-secondary">
                                                    {user.email}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <RoleBadge
                                                        role={user.role}
                                                    />
                                                </td>
                                                <td className="hidden max-w-48 px-3 py-2 wrap-anywhere xl:table-cell">
                                                    {user.relationSummary ||
                                                        '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <StatusBadge user={user} />
                                                </td>
                                                <td className="hidden px-3 py-2 text-text-secondary xl:table-cell">
                                                    {formatDateTime(
                                                        user.lastLoginAt,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <Link
                                                        href={`/admin/users/${user.id}`}
                                                        aria-label={`Szczegóły: ${fullName(user)}`}
                                                        className="inline-flex size-11 items-center justify-center rounded-md text-text-secondary outline-none hover:bg-surface-secondary focus-visible:ring-[3px] focus-visible:ring-focus"
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <ul className="divide-y divide-border rounded-lg border border-border bg-surface md:hidden">
                                {users.data.map((user) => (
                                    <li key={user.id}>
                                        <Link
                                            href={`/admin/users/${user.id}`}
                                            aria-label={`Szczegóły: ${fullName(user)}`}
                                            className="flex min-h-16 items-center gap-3 px-4 py-3 outline-none focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:ring-inset"
                                        >
                                            <Avatar text={initials(user)} />
                                            <span className="min-w-0 flex-1">
                                                <span className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium wrap-anywhere">
                                                        {fullName(user)}
                                                    </span>
                                                    {user.status !== 'active' ||
                                                    user.deletedAt ? (
                                                        <StatusBadge
                                                            user={user}
                                                        />
                                                    ) : null}
                                                </span>
                                                <span className="block text-xs wrap-anywhere text-text-secondary">
                                                    {roleLabels[user.role]}
                                                    {user.relationSummary
                                                        ? `, ${user.relationSummary}`
                                                        : ''}
                                                </span>
                                            </span>
                                            <ChevronRight
                                                className="size-4 shrink-0 text-text-secondary"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                    <Pagination page={users} label="Strony użytkowników" />
                </section>
            </div>
        </>
    );
}

function Avatar({ text }: { text: string }) {
    return (
        <span
            aria-hidden="true"
            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-xs font-medium text-primary"
        >
            {text}
        </span>
    );
}

function UserFilterForm({
    filters,
    options,
    activeFilters,
    onPending,
}: {
    filters: UserFilters;
    options: UserListProps['options'];
    activeFilters: number;
    onPending: (pending: boolean) => void;
}) {
    const [q, setQ] = useState(filters.q);
    const [role, setRole] = useState<string>(filters.role ?? '');
    const [status, setStatus] = useState<string>(filters.status ?? '');
    const [schoolClassId, setSchoolClassId] = useState(
        filters.schoolClassId ?? '',
    );
    const [termId, setTermId] = useState(filters.termId ?? '');
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const panelId = useId();
    const yearId = options.classes.find(
        (item) => item.id === schoolClassId,
    )?.academicYearId;
    const terms = options.terms.filter(
        (term) => term.academicYearId === yearId,
    );

    function changeClass(value: string) {
        setSchoolClassId(value);
        setTermId('');
    }

    return (
        <form
            role="search"
            aria-label="Filtry użytkowników"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    '/admin/users',
                    {
                        q: q.trim() || undefined,
                        role: role || undefined,
                        status: status || undefined,
                        school_class_id: schoolClassId || undefined,
                        term_id: schoolClassId
                            ? termId || undefined
                            : undefined,
                        per_page:
                            filters.perPage === 10
                                ? undefined
                                : filters.perPage,
                    },
                    {
                        onStart: () => {
                            setPending(true);
                            onPending(true);
                        },
                        onFinish: () => {
                            setPending(false);
                            onPending(false);
                        },
                    },
                );
            }}
            className="space-y-3"
        >
            <div className="flex flex-wrap items-end gap-3">
                <div className="relative min-w-0 flex-1 space-y-1.5 md:min-w-64">
                    <Label htmlFor="user-search">
                        Szukaj po nazwisku lub e-mailu
                    </Label>
                    <Search
                        className="pointer-events-none absolute bottom-3.5 left-3 size-4 text-text-secondary"
                        aria-hidden="true"
                    />
                    <Input
                        id="user-search"
                        type="search"
                        value={q}
                        maxLength={100}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Szukaj po nazwisku lub e-mailu…"
                        className="pl-9"
                    />
                </div>
                <Button
                    type="button"
                    variant="secondary"
                    className="md:hidden"
                    aria-expanded={open}
                    aria-controls={panelId}
                    onClick={() => setOpen((value) => !value)}
                >
                    <ListFilter aria-hidden="true" />
                    Filtry{activeFilters ? ` (${activeFilters})` : ''}
                </Button>
                <Button type="submit" loading={pending}>
                    Szukaj
                </Button>
            </div>
            <div
                id={panelId}
                className={`${open ? 'grid' : 'hidden'} gap-3 md:grid md:grid-cols-2 xl:grid-cols-4`}
            >
                <OptionField
                    id="filter-role"
                    label="Rola"
                    value={role}
                    onChange={setRole}
                    options={options.roles.map((item) => ({
                        id: item,
                        label: roleLabels[item],
                    }))}
                    optional
                />
                <OptionField
                    id="filter-status"
                    label="Status"
                    value={status}
                    onChange={setStatus}
                    options={statusOptions}
                    optional
                />
                <OptionField
                    id="filter-class"
                    label="Klasa"
                    value={schoolClassId}
                    onChange={changeClass}
                    options={options.classes}
                    optional
                />
                {schoolClassId && (
                    <OptionField
                        id="filter-term"
                        label="Semestr (wymagany z klasą)"
                        value={termId}
                        onChange={setTermId}
                        options={terms}
                    />
                )}
            </div>
        </form>
    );
}
