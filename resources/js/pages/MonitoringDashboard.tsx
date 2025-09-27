import React from 'react';
import { Head } from '@inertiajs/react';
import MonitoringDashboard from '@/components/MonitoringDashboard';
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout';

export default function MonitoringDashboardPage() {
  return (
    <AuthenticatedLayout>
      <Head title="系統監控" />
      <MonitoringDashboard />
    </AuthenticatedLayout>
  );
}
