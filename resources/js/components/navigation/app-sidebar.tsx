import { Link } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Brand, BrandMark } from '@/components/brand';
import { sidebarIcons } from '@/components/navigation/icons';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { Navigation, NavigationItem, User } from '@/types';

export const roleLabels: Record<User['role'], string> = {
    student: 'Uczeń',
    teacher: 'Nauczyciel',
    admin: 'Administrator',
};

function SidebarLink({
    item,
    collapsed,
}: {
    item: NavigationItem;
    collapsed: boolean;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const Icon = sidebarIcons[item.icon];
    const active = item.active ?? isCurrentUrl(item.href);

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            aria-label={collapsed ? item.label : undefined}
            title={collapsed ? item.label : undefined}
            className={cn(
                'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-nav-text-muted outline-none hover:text-nav-text focus-visible:ring-[3px] focus-visible:ring-focus',
                collapsed && 'justify-center px-0',
                active && 'bg-nav-active text-nav-text',
            )}
        >
            <Icon aria-hidden="true" className="size-5 shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
        </Link>
    );
}

/** Desktop: 248px (Figma 7:358). Tablet rail: 72px (Figma 38:8368). */
export function AppSidebar({
    navigation,
    user,
    collapsed,
}: {
    navigation: Navigation;
    user: User;
    collapsed: boolean;
}) {
    const initials = useInitials()(user.name);

    return (
        <aside
            data-collapsed={collapsed}
            className={cn(
                'sticky top-0 flex h-svh shrink-0 flex-col bg-nav-bg py-6 text-nav-text',
                collapsed ? 'w-18 px-3' : 'w-62 px-4',
            )}
        >
            <div
                className={cn(
                    'mb-6',
                    collapsed ? 'flex justify-center' : 'px-2',
                )}
            >
                {collapsed ? <BrandMark /> : <Brand />}
            </div>
            <nav aria-label="Główna nawigacja" className="flex-1">
                <ul className="space-y-1">
                    {navigation.primary.map((item) => (
                        <li key={item.key}>
                            <SidebarLink item={item} collapsed={collapsed} />
                        </li>
                    ))}
                </ul>
            </nav>
            <nav aria-label="Konto">
                <ul className="space-y-1">
                    {navigation.secondary.map((item) => (
                        <li key={item.key}>
                            <SidebarLink item={item} collapsed={collapsed} />
                        </li>
                    ))}
                </ul>
            </nav>
            <div
                className={cn(
                    'mt-3 flex items-center gap-3 border-t border-nav-active pt-3',
                    collapsed && 'flex-col',
                )}
            >
                <span
                    aria-hidden="true"
                    className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-xs font-semibold text-primary"
                >
                    {initials}
                </span>
                {!collapsed && (
                    <div className="min-w-0 flex-1">
                        <p
                            className="truncate text-sm leading-5 font-medium"
                            title={user.name}
                        >
                            {user.name}
                        </p>
                        <p className="truncate text-xs leading-4 text-nav-text-muted">
                            {roleLabels[user.role]}
                        </p>
                    </div>
                )}
                <LogoutButton />
            </div>
        </aside>
    );
}

export function LogoutButton({ className }: { className?: string }) {
    return (
        <Link
            href="/logout"
            method="post"
            as="button"
            aria-label="Wyloguj"
            title="Wyloguj"
            data-test="logout-button"
            className={cn(
                'flex size-11 shrink-0 items-center justify-center rounded-md text-nav-text-muted outline-none hover:text-nav-text focus-visible:ring-[3px] focus-visible:ring-focus',
                className,
            )}
        >
            <LogOut aria-hidden="true" className="size-5" />
        </Link>
    );
}
