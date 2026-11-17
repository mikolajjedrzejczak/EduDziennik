import { AppShell } from '@/components/app-shell';
import { StudentGradesMobileHeader } from '@/components/grades/student-grades-mobile-header';
import { usePage } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { component } = usePage();

    return (
        <AppShell
            breadcrumbs={breadcrumbs}
            mobileHeader={
                component === 'student/grades/index' ? (
                    <StudentGradesMobileHeader />
                ) : undefined
            }
        >
            {children}
        </AppShell>
    );
}
