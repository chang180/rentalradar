import React from 'react';

interface PerformanceData {
    timestamp: number;
    responseTime: number;
    memoryUsage: number;
    queryCount: number;
    cacheHitRate: number;
    activeConnections: number;
    errorRate: number;
    throughput: number;
}

interface PerformanceChartProps {
    data: PerformanceData[];
    metric: string;
    title: string;
    color: string;
    type?: 'line' | 'area' | 'bar';
    height?: number;
}

export const PerformanceChart: React.FC<PerformanceChartProps> = ({
    data,
    metric,
    title,
    color,
    type = 'line',
    height = 300,
}) => {
    if (!data || data.length === 0) {
        return (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center text-gray-500 dark:text-gray-400">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">{title}</h3>
                <p>無可用資料</p>
            </div>
        );
    }

    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">{title}</h3>
            <div className="h-64 flex items-center justify-center text-gray-500 dark:text-gray-400">
                <p>圖表功能暫時停用</p>
            </div>
        </div>
    );
};

interface MultiMetricChartProps {
    data: PerformanceData[];
    metrics: { key: string; title: string; color: string }[];
    type?: 'line' | 'area' | 'bar';
    height?: number;
}

export const MultiMetricChart: React.FC<MultiMetricChartProps> = ({
    data,
    metrics,
    type = 'line',
    height = 300,
}) => {
    if (!data || data.length === 0) {
        return (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center text-gray-500 dark:text-gray-400">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">多指標綜合趨勢</h3>
                <p>無可用資料</p>
            </div>
        );
    }

    return (
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">多指標綜合趨勢</h3>
            <div className="h-64 flex items-center justify-center text-gray-500 dark:text-gray-400">
                <p>圖表功能暫時停用</p>
            </div>
        </div>
    );
};
