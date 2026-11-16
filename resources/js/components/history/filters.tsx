import { useFilterState } from '@/hooks/use-filter-state';
import { router, usePage } from '@inertiajs/react';
import { SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { FormField } from '@/components/forms/form-field';
import { OptionField } from '@/components/school/option-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useViewport } from '@/hooks/use-viewport';
import { usePageLoading } from '@/hooks/use-page-loading';
import type { HistoryProps } from '@/types/history';

export const actionLabels = {
    create: 'Dodanie',
    update: 'Zmiana',
    delete: 'Usunięcie',
};

export function HistoryFilters({
    filters,
    options,
    indexUrl,
}: Pick<HistoryProps, 'filters' | 'options' | 'indexUrl'>) {
    const { errors } = usePage().props;
    const [open, setOpen] = useState(false);
    const mobile = useViewport() === 'mobile';
    const pending = usePageLoading();
    const [term, setTerm] = useFilterState(filters.termId ?? '');
    const [subject, setSubject] = useFilterState(filters.subjectId ?? '');
    const [action, setAction] = useFilterState(filters.action ?? '');
    const [from, setFrom] = useFilterState(filters.dateFrom ?? '');
    const [to, setTo] = useFilterState(filters.dateTo ?? '');
    const reset = () => {
        setTerm('');
        setSubject('');
        setAction('');
        setFrom('');
        setTo('');
    };
    const form = (
        <form
            role="search"
            aria-label="Filtry historii"
            className="grid gap-4 md:grid-cols-3 xl:grid-cols-6 xl:items-end"
            onSubmit={(event) => {
                event.preventDefault();
                router.get(
                    indexUrl,
                    {
                        ...(filters.gradeId
                            ? { grade_id: filters.gradeId }
                            : {}),
                        ...(filters.studentEnrollmentId
                            ? {
                                  student_enrollment_id:
                                      filters.studentEnrollmentId,
                              }
                            : {}),
                        ...(term ? { term_id: term } : {}),
                        ...(subject ? { subject_id: subject } : {}),
                        ...(action ? { action } : {}),
                        ...(from ? { date_from: from } : {}),
                        ...(to ? { date_to: to } : {}),
                        per_page: filters.perPage,
                    },
                    { preserveScroll: true, onSuccess: () => setOpen(false) },
                );
            }}
        >
            <OptionField
                id="history-term"
                label="Semestr"
                value={term}
                options={options.terms}
                onChange={setTerm}
                optional
                error={errors.term_id}
            />
            <OptionField
                id="history-subject"
                label="Przedmiot"
                value={subject}
                options={options.subjects}
                onChange={setSubject}
                optional
                error={errors.subject_id}
            />
            <OptionField
                id="history-action"
                label="Operacja"
                value={action}
                options={Object.entries(actionLabels).map(([id, label]) => ({
                    id,
                    label,
                }))}
                onChange={setAction}
                optional
                error={errors.action}
            />
            <FormField
                id="history-from"
                label="Data od"
                error={errors.date_from}
            >
                {(attrs) => (
                    <Input
                        {...attrs}
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                    />
                )}
            </FormField>
            <FormField id="history-to" label="Data do" error={errors.date_to}>
                {(attrs) => (
                    <Input
                        {...attrs}
                        type="date"
                        value={to}
                        min={from || undefined}
                        onChange={(e) => setTo(e.target.value)}
                    />
                )}
            </FormField>
            <div className="flex flex-wrap gap-2">
                <Button disabled={pending} type="submit">
                    Pokaż wyniki
                </Button>
                <Button type="button" variant="ghost" onClick={reset}>
                    Wyczyść
                </Button>
            </div>
        </form>
    );
    if (!mobile) return form;
    return (
        <Dialog.Root open={open} onOpenChange={setOpen}>
            <Dialog.Trigger asChild>
                <Button variant="secondary">
                    <SlidersHorizontal aria-hidden="true" /> Filtry
                </Button>
            </Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-nav-bg/40" />
                <Dialog.Content
                    className="fixed inset-x-0 bottom-0 z-50 max-h-[90dvh] overflow-y-auto rounded-t-2xl bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                    aria-describedby={undefined}
                >
                    <div className="mb-4 flex items-center justify-between">
                        <Dialog.Title className="text-lg font-semibold">
                            Filtry historii
                        </Dialog.Title>
                        <Dialog.Close asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label="Zamknij filtry"
                            >
                                <X />
                            </Button>
                        </Dialog.Close>
                    </div>
                    {form}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
