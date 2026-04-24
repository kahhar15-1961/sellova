import { usePage } from '@inertiajs/react';

/**
 * @returns {(permission: string) => boolean}
 */
export function useAdminCan() {
    const can = usePage().props.can ?? {};
    return (permission) => Boolean(can[permission]);
}
