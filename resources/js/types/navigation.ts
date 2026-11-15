import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type NavigationIcon = 'dashboard' | 'profile';

/** Built by the server (NavigationService); the client only renders it. */
export type NavigationItem = {
    key: string;
    label: string;
    shortLabel: string;
    href: string;
    icon: NavigationIcon;
};

export type Navigation = {
    primary: NavigationItem[];
    secondary: NavigationItem[];
};
