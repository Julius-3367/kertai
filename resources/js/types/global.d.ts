import type { Auth, SharedGroup } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            group: SharedGroup | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
        flashDataType: {
            toast?: {
                type: 'success' | 'info' | 'warning' | 'error';
                message: string;
            };
            payment_key?: string;
        };
    }
}
