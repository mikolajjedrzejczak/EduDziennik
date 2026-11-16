import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    History,
    GraduationCap,
    LayoutGrid,
    SlidersHorizontal,
    User,
    Users,
} from 'lucide-react';
import type { NavigationIcon } from '@/types';

/** Server sends only an allowlisted key; the client owns the glyph. */
export const sidebarIcons: Record<NavigationIcon, LucideIcon> = {
    dashboard: LayoutGrid,
    classes: GraduationCap,
    profile: SlidersHorizontal,
    subjects: BookOpen,
    assignments: Users,
    users: Users,
    grades: BookOpen,
    history: History,
};

export const mobileIcons: Record<NavigationIcon, LucideIcon> = {
    dashboard: LayoutGrid,
    classes: GraduationCap,
    profile: User,
    subjects: BookOpen,
    assignments: Users,
    users: Users,
    grades: BookOpen,
    history: History,
};
