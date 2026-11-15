import { useForm, usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Trash, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { ErrorSummary } from '@/components/forms/error-summary';
import { FormField } from '@/components/forms/form-field';
import { SubmitButton } from '@/components/forms/submit-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { fullName, roleLabels } from '@/lib/users';
import type { UserDTO } from '@/types/users';

/** Figma 19:1752 / 24:1137: desktop dialog and mobile bottom sheet. */
export function DeleteUserDialog({ user }: { user: UserDTO }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ confirmation_last_name: '' });
    const inputRef = useRef<HTMLInputElement>(null);
    const id = useId();
    if (user.deletedAt || String(auth.user.id) === user.id) return null;

    function close() {
        if (form.processing) return;
        setOpen(false);
        form.reset();
        form.clearErrors();
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => (value ? setOpen(true) : close())}
        >
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Usuń konto: ${fullName(user)}`}
                    className="shrink-0 text-error"
                >
                    <Trash aria-hidden="true" className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogPortal>
                <DialogOverlay className="bg-nav-bg/45" />
                <DialogPrimitive.Content
                    className="fixed inset-x-0 bottom-0 z-50 max-h-[100dvh] overflow-y-auto rounded-t-2xl bg-surface text-text-primary shadow-overlay md:inset-x-auto md:top-1/2 md:bottom-auto md:left-1/2 md:max-h-[calc(100dvh-2rem)] md:w-[480px] md:max-w-[calc(100%-2rem)] md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-xl"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        inputRef.current?.focus();
                    }}
                    onEscapeKeyDown={(event) => {
                        if (form.processing) event.preventDefault();
                    }}
                    onInteractOutside={(event) => {
                        if (form.processing) event.preventDefault();
                    }}
                >
                    <div
                        aria-hidden="true"
                        className="mx-auto mt-2 h-1 w-9 rounded-full bg-border-strong md:hidden"
                    />
                    <form
                        noValidate
                        aria-busy={form.processing}
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (form.processing) return;
                            form.delete(`/admin/users/${user.id}`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
                            });
                        }}
                    >
                        <div className="space-y-4 px-5 pt-4 pb-5 md:space-y-3 md:px-6 md:pt-6">
                            <div className="flex items-start gap-2">
                                <DialogTitle className="min-w-0 flex-1 text-lg leading-[26px] font-semibold wrap-anywhere">
                                    Usunąć konto: {fullName(user)}?
                                </DialogTitle>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Zamknij potwierdzenie"
                                    disabled={form.processing}
                                    onClick={close}
                                    className="-mt-2 -mr-3 text-text-primary md:hidden"
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </div>
                            <DialogDescription className="text-sm leading-5 text-text-secondary">
                                Użytkownik straci dostęp do EduDziennika. Oceny
                                i historia zmian zostaną zachowane.
                            </DialogDescription>
                            <dl className="hidden space-y-1.5 rounded-md bg-surface-secondary px-3.5 py-3 text-[13px] leading-[18px] md:block">
                                {[
                                    ['Rola', roleLabels[user.role]],
                                    ['E-mail', user.email],
                                    [
                                        'Status',
                                        user.status === 'active'
                                            ? 'Aktywne'
                                            : 'Nieaktywne',
                                    ],
                                ].map(([label, value]) => (
                                    <div key={label} className="flex gap-2">
                                        <dt className="w-20 shrink-0 text-text-secondary">
                                            {label}
                                        </dt>
                                        <dd className="min-w-0 wrap-anywhere">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <ErrorSummary
                                errors={form.errors}
                                fields={{
                                    confirmation_last_name: {
                                        id,
                                        label: 'Nazwisko potwierdzenia',
                                    },
                                }}
                                title="Nie udało się usunąć konta"
                            />
                            <FormField
                                id={id}
                                label={`Aby potwierdzić, wpisz nazwisko: ${user.lastName}`}
                                error={form.errors.confirmation_last_name}
                            >
                                {(attributes) => (
                                    <Input
                                        {...attributes}
                                        ref={inputRef}
                                        autoComplete="off"
                                        maxLength={80}
                                        value={form.data.confirmation_last_name}
                                        disabled={form.processing}
                                        onChange={(event) =>
                                            form.setData(
                                                'confirmation_last_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>
                        <div className="flex flex-col-reverse gap-4 px-5 pb-[max(1.75rem,env(safe-area-inset-bottom))] md:flex-row md:justify-end md:gap-2 md:bg-surface-secondary md:px-6 md:py-4">
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={form.processing}
                                onClick={close}
                            >
                                Anuluj
                            </Button>
                            <SubmitButton
                                variant="destructive"
                                processing={form.processing}
                            >
                                <Trash aria-hidden="true" className="size-4" />
                                Usuń konto
                            </SubmitButton>
                        </div>
                    </form>
                </DialogPrimitive.Content>
            </DialogPortal>
        </Dialog>
    );
}
