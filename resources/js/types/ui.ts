import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

export type ErrorStatus = 403 | 404 | 419 | 429 | 500 | 503;

/** Contract: docs/http/REQUESTS_RESPONSES.md (ErrorProps). */
export type ErrorPageProps = {
    status: ErrorStatus;
    message: string;
    correlationId?: string;
    retryAfter?: number;
    /** Present only for a safe GET that may be repeated (503). */
    retryUrl?: string;
    auth?: { user: unknown; role: string | null };
};
