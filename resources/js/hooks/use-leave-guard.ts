import { router } from '@inertiajs/react';
import { useEffect } from 'react';

const MESSAGE = 'You have changes that are not saved. Leave this page?';

/**
 * Asks before leaving a page with unsaved typing: a tap on another page or
 * closing the tab. The form's own submit (not a GET), prefetches and partial
 * reloads go through untouched.
 * TODO: the browser back button restores the previous page from history
 * without an Inertia visit, so it isn't caught here.
 */
export function useLeaveGuard(dirty: boolean) {
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                visit.method !== 'get' ||
                visit.prefetch ||
                visit.only.length > 0
            ) {
                return;
            }

            if (!window.confirm(MESSAGE)) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            off();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    }, [dirty]);
}
