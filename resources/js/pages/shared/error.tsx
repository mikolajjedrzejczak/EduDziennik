import { Head, Link, router } from '@inertiajs/react';
import { Lock, SearchX } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { ErrorState } from '@/components/states/error-state';
import { Button } from '@/components/ui/button';
import type { ErrorPageProps, ErrorStatus } from '@/types/ui';

const titles: Record<ErrorStatus, string> = {
    403: 'Nie masz dostępu do tej strony',
    404: 'Nie znaleziono strony',
    419: 'Sesja wygasła',
    429: 'Zbyt wiele prób',
    500: 'Coś poszło nie tak',
    503: 'Usługa jest chwilowo niedostępna',
};

function Frame({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-bg text-text-primary">
            <header className="px-6 py-5 text-sm font-semibold">
                EduDziennik
            </header>
            <main className="flex flex-1 items-start justify-center px-4 pt-16 md:items-center md:pt-0">
                <div className="w-full max-w-xl">{children}</div>
            </main>
        </div>
    );
}

export default function SharedError({
    status,
    message,
    correlationId,
    retryAfter,
    retryUrl,
    auth,
}: ErrorPageProps) {
    const [retrying, setRetrying] = useState(false);
    const signedIn = Boolean(auth?.user);
    const home = signedIn
        ? { href: '/dashboard', label: 'Wróć do pulpitu' }
        : { href: '/login', label: 'Przejdź do logowania' };
    const title = titles[status];

    if (status === 403 || status === 404) {
        const Icon = status === 403 ? Lock : SearchX;

        return (
            <Frame>
                <Head title={title} />
                <div className="flex flex-col items-start gap-4">
                    <span className="flex size-10 items-center justify-center rounded-lg bg-warning-bg text-warning">
                        <Icon aria-hidden="true" className="size-5" />
                    </span>
                    <p className="text-xs text-text-secondary">Błąd {status}</p>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-text-secondary">{message}</p>
                    <div className="flex w-full flex-col gap-3 md:w-auto md:flex-row">
                        <Button asChild>
                            <Link href={home.href}>{home.label}</Link>
                        </Button>
                    </div>
                </div>
            </Frame>
        );
    }

    const canRetry = status === 503 && Boolean(retryUrl);
    const detail =
        status === 429 && retryAfter
            ? `${message} Spróbuj ponownie za ${retryAfter} s.`
            : message;

    return (
        <Frame>
            <Head title={title} />
            <h1 className="sr-only">{title}</h1>
            <ErrorState
                title={title}
                description={detail}
                correlationId={correlationId}
                retrying={retrying}
                onRetry={
                    canRetry
                        ? () => {
                              if (retrying || !retryUrl) return;
                              setRetrying(true);
                              router.visit(retryUrl, {
                                  method: 'get',
                                  onFinish: () => setRetrying(false),
                              });
                          }
                        : undefined
                }
            >
                {status === 503 && !canRetry && (
                    <p className="text-sm text-text-secondary">
                        Sprawdź aktualny stan danych, zanim ponowisz zapis.
                    </p>
                )}
                <Button asChild variant="secondary" className="mt-3">
                    <Link href={home.href}>{home.label}</Link>
                </Button>
            </ErrorState>
        </Frame>
    );
}
