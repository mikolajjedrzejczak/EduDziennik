import { AppShell } from '@/components/app-shell';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return <AppShell breadcrumbs={breadcrumbs}>{children}</AppShell>;
}
