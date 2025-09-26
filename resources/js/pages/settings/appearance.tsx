import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: '外觀設定',
        href: editAppearance().url,
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="外觀設定" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="外觀設定"
                        description="更新您帳戶的外觀設定"
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
