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
            <div className="rounded-lg bg-white p-6 text-center text-gray-500 shadow dark:bg-gray-800 dark:text-gray-400">
                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    {title}
                </h3>
                <p>無可用資料</p>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                {title}
            </h3>
            <div className="flex h-64 items-center justify-center text-gray-500 dark:text-gray-400">
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
            <div className="rounded-lg bg-white p-6 text-center text-gray-500 shadow dark:bg-gray-800 dark:text-gray-400">
                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    多指標綜合趨勢
                </h3>
                <p>無可用資料</p>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                多指標綜合趨勢
            </h3>
            <div className="flex h-64 items-center justify-center text-gray-500 dark:text-gray-400">
                <p>圖表功能暫時停用</p>
            </div>
        </div>
    );
};
