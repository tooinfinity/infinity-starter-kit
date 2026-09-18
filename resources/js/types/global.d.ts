import type { Auth } from '@/types/auth';
/* @chisel-localization */
import type { Direction, LocaleOption } from '@/types/localization';
/* @end-chisel-localization */
/* @chisel-notifications */
import type { NotificationsSharedData } from '@/types/notifications';
/* @end-chisel-notifications */
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /* @chisel-localization */
            locale: string;
            direction: Direction;
            supportedLocales: LocaleOption[];
            /* @end-chisel-localization */
            /* @chisel-notifications */
            notifications: NotificationsSharedData;
            /* @end-chisel-notifications */
            [key: string]: unknown;
        };
    }
}
