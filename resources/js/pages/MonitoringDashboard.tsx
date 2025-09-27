import React from 'react';
import { Head } from '@inertiajs/react';
import MonitoringDashboard from '@/components/MonitoringDashboard';
import AppLayout from '@/layouts/app-layout';

export default function MonitoringDashboardPage() {
  return (
    <AppLayout>
      <Head title="系統監控" />
      <MonitoringDashboard />
    </AppLayout>
  );
}
