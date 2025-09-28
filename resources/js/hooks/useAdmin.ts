import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { type SharedData, type User } from '@/types';

interface AdminPermissions {
    isAdmin: boolean;
    canUpload: boolean;
    canManageSchedules: boolean;
    canManageUsers: boolean;
    canViewPerformance: boolean;
}

export function useAdmin(): AdminPermissions {
    const page = usePage<SharedData>();
    const user = page.props.auth.user as User & { is_admin?: boolean };

    return useMemo(() => {
        const isAdmin = Boolean(user?.is_admin);

        return {
            isAdmin,
            canUpload: isAdmin,
            canManageSchedules: isAdmin,
            canManageUsers: isAdmin,
            canViewPerformance: isAdmin,
        };
    }, [user?.is_admin]);
}

export function useAdminCheck(): boolean {
    const { isAdmin } = useAdmin();
    return isAdmin;
}
