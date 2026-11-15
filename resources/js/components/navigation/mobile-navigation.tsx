import { Link } from '@inertiajs/react';
import { mobileIcons } from '@/components/navigation/icons';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { Navigation } from '@/types';

/** Bottom bar (Figma 7:534). Labels stay visible; 44px+ hit targets; safe-area aware. */
export function MobileNavigation({ navigation }: { navigation: Navigation }) {
    const { isCurrentUrl } = useCurrentUrl();
    const items = [...navigation.primary, ...navigation.secondary];

    return (
        <nav
            aria-label="Nawigacja mobilna"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]"
        >
            <ul className="flex">
                {items.map((item) => {
                    const Icon = mobileIcons[item.icon];
                    const active = isCurrentUrl(item.href);

                    return (
                        <li key={item.key} className="min-w-0 flex-1">
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-xs font-medium text-text-secondary outline-none focus-visible:ring-[3px] focus-visible:ring-focus focus-visible:ring-inset"
                            >
                                <span
                                    className={cn(
                                        'flex h-7 w-14 items-center justify-center rounded-full',
                                        active &&
                                            'bg-primary-subtle text-primary',
                                    )}
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </span>
                                <span
                                    className={cn(
                                        'max-w-full truncate',
                                        active && 'text-text-primary',
                                    )}
                                >
                                    {item.shortLabel}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
