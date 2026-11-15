import type { LucideIcon } from 'lucide-react';
import {
    GraduationCap,
    LayoutGrid,
    SlidersHorizontal,
    User,
} from 'lucide-react';
import type { NavigationIcon } from '@/types';

/** Server sends only an allowlisted key; the client owns the glyph. */
export const sidebarIcons: Record<NavigationIcon, LucideIcon> = {
    dashboard: LayoutGrid,
    classes: GraduationCap,
    profile: SlidersHorizontal,
};

export const mobileIcons: Record<NavigationIcon, LucideIcon> = {
    dashboard: LayoutGrid,
    classes: GraduationCap,
    profile: User,
};
