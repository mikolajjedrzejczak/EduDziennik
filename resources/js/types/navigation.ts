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

export type NavigationIcon =
    | 'dashboard'
    | 'profile'
    | 'classes'
    | 'subjects'
    | 'assignments'
    | 'users'
    | 'grades'
    | 'history';

/** Built by the server (NavigationService); the client only renders it. */
export type NavigationItem = {
    key: string;
    label: string;
    shortLabel: string;
    href: string;
    icon: NavigationIcon;
    active?: boolean;
};

export type Navigation = {
    primary: NavigationItem[];
    secondary: NavigationItem[];
};
