import { usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { ActionButton } from '@/components/kertai/actions';

/** Shown on every page while someone previews another position. */
export function ViewAsBanner() {
    const { auth } = usePage().props;

    if (!auth?.viewing_as) {
        return null;
    }

    return (
        <div className="sticky top-0 z-40 flex flex-wrap items-center justify-between gap-2 border-b border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <span className="flex items-center gap-2">
                <Eye className="size-4 shrink-0" />
                Viewing as {auth.viewing_as}. Look only; changes are switched
                off.
            </span>
            <ActionButton url="/view-as/stop" size="sm" variant="outline">
                Exit preview
            </ActionButton>
        </div>
    );
}
