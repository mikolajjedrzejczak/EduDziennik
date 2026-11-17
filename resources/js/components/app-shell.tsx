import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Brand } from '@/components/brand';
import { AppSidebar, LogoutButton } from '@/components/navigation/app-sidebar';
import { MobileNavigation } from '@/components/navigation/mobile-navigation';
import { useTextEntryFocus } from '@/hooks/use-text-entry-focus';
import { useViewport } from '@/hooks/use-viewport';
import type { BreadcrumbItem } from '@/types';

/**
 * Role shell (F-007): sidebar 248 on desktop, 72 rail on tablet, bottom bar on
 * mobile. Exactly one navigation is in the DOM. Menu content comes from the
 * server; without it (error/guest states) only the content frame renders.
 */
export function AppShell({
    children,
    breadcrumbs = [],
    mobileHeader,
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    mobileHeader?: ReactNode;
}) {
    const { auth, navigation } = usePage().props;
    const viewport = useViewport();
    const typing = useTextEntryFocus();
    const mobile = viewport === 'mobile';

    return (
        <div className="flex min-h-svh bg-bg text-text-primary">
            <a
                href="#main-content"
                className="sr-only rounded-md bg-surface px-4 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:ring-[3px] focus:ring-focus"
            >
                Przejdź do treści
            </a>
            {navigation && auth.user && !mobile && (
                <AppSidebar
                    navigation={navigation}
                    user={auth.user}
                    collapsed={viewport === 'tablet'}
                />
            )}
            <div className="flex min-w-0 flex-1 flex-col">
                {mobile &&
                    (mobileHeader ?? (
                        <header className="flex h-14 shrink-0 items-center justify-between border-b border-border bg-surface pr-1 pl-4">
                            <span className="min-w-0 truncate text-text-primary">
                                <Brand />
                            </span>
                            {auth.user && (
                                <LogoutButton className="text-text-secondary hover:text-text-primary" />
                            )}
                        </header>
                    ))}
                {!mobile && breadcrumbs.length > 0 && (
                    <header className="flex h-14 shrink-0 items-center border-b border-border px-6">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </header>
                )}
                <main
                    id="main-content"
                    tabIndex={-1}
                    className="min-w-0 flex-1 outline-none"
                    style={
                        mobile && navigation
                            ? {
                                  paddingBottom:
                                      'calc(4.5rem + env(safe-area-inset-bottom))',
                              }
                            : undefined
                    }
                >
                    {children}
                </main>
            </div>
            {mobile && navigation && !typing && (
                <MobileNavigation navigation={navigation} />
            )}
        </div>
    );
}
