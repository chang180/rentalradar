import { route } from '@inertiajs/react';

export const dashboard = {
    url: () => route('dashboard'),
};

export const admin = {
    users: () => route('admin.users'),
    uploads: () => route('admin.uploads'),
    schedules: () => route('admin.schedules'),
    permissions: () => route('admin.permissions'),
};
