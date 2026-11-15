import type { Auth, Capabilities } from '@/types/auth';

declare module 'react' {
    // Module augmentation must repeat React's generic parameter.
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            can: Capabilities;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
