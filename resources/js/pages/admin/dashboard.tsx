import { Head } from '@inertiajs/react';
import { EmptyState } from '@/components/states/empty-state';

export default function Dashboard() {
    return (
        <>
            <Head title="Pulpit" />
            <div className="mx-auto w-full max-w-5xl px-4 py-6 md:px-6 md:py-8">
                <h1 className="text-h1 font-semibold break-words">
                    Pulpit administratora
                </h1>
                <section
                    aria-label="Podsumowanie"
                    className="mt-6 rounded-lg border border-border bg-surface"
                >
                    <EmptyState
                        variant="no-data"
                        title="Brak danych do wyświetlenia"
                        description="Tu pojawią się podsumowania kont, klas i przedmiotów."
                    />
                </section>
            </div>
        </>
    );
}
