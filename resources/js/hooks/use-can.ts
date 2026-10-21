import { usePage } from '@inertiajs/react';

/** Checks the signed-in member's position permissions. */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];

    return (permission: string) => permissions.includes(permission);
}
