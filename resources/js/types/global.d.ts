import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

declare global {
    interface SharedPageProps {
        name: string;
        timezone: string;
        auth: Auth;
        sidebarOpen: boolean;
        [key: string]: unknown;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: SharedPageProps;
    }
}
