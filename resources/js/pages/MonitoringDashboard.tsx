import MonitoringDashboard from '@/components/MonitoringDashboard';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function MonitoringDashboardPage() {
    return (
        <AppLayout>
            <Head title="系統監控" />
            <MonitoringDashboard />
        </AppLayout>
    );
}
