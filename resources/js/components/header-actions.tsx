/* @chisel-localization */
import { LanguageSelector } from '@/components/language-selector';
/* @end-chisel-localization */
/* @chisel-notifications */
import { NotificationBell } from '@/components/notifications/notification-bell';
/* @end-chisel-notifications */

export function HeaderActions() {
    return (
        <>
            {/* @chisel-notifications */}
            <NotificationBell />
            {/* @end-chisel-notifications */}
            {/* @chisel-localization */}
            <LanguageSelector />
            {/* @end-chisel-localization */}
        </>
    );
}
