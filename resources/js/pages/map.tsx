import React from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '../layouts/app-layout';
import RentalMap from '../components/rental-map';

export default function Map() {
    return (
        <AppLayout>
            <Head title="租屋地圖" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            租屋地圖
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            透過互動式地圖探索台北市租屋市場
                        </p>
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <RentalMap />
                </div>
            </div>
        </AppLayout>
    );
}