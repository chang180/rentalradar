import React from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

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
    type?: 'line' | 'area' | 'bar';
    metric: keyof PerformanceData;
    title: string;
    color?: string;
    height?: number;
}

export const PerformanceChart: React.FC<PerformanceChartProps> = ({
    data,
    type = 'line',
    metric,
    title,
    color = '#3B82F6',
    height = 300,
}) => {
    const formatTime = (timestamp: number): string => {
        return new Date(timestamp).toLocaleTimeString('zh-TW', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatValue = (value: number): string => {
        if (metric === 'responseTime') {
            return `${value.toFixed(0)}ms`;
        }
        if (metric === 'memoryUsage') {
            return `${value.toFixed(1)}MB`;
        }
        if (metric === 'cacheHitRate') {
            return `${value.toFixed(1)}%`;
        }
        if (metric === 'errorRate') {
            return `${value.toFixed(2)}%`;
        }
        return value.toString();
    };

    const chartData = data.map((item) => ({
        time: formatTime(item.timestamp),
        value: item[metric],
        timestamp: item.timestamp,
    }));

    const renderChart = () => {
        const commonProps = {
            data: chartData,
            margin: { top: 5, right: 30, left: 20, bottom: 5 },
        };

        switch (type) {
            case 'area':
                return (
                    <AreaChart {...commonProps}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="time" />
                        <YAxis />
                        <Tooltip
                            formatter={(value: number) => [
                                formatValue(value),
                                title,
                            ]}
                            labelFormatter={(label) => `時間: ${label}`}
                        />
                        <Area
                            type="monotone"
                            dataKey="value"
                            stroke={color}
                            fill={color}
                            fillOpacity={0.3}
                        />
                    </AreaChart>
                );
            case 'bar':
                return (
                    <BarChart {...commonProps}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="time" />
                        <YAxis />
                        <Tooltip
                            formatter={(value: number) => [
                                formatValue(value),
                                title,
                            ]}
                            labelFormatter={(label) => `時間: ${label}`}
                        />
                        <Bar dataKey="value" fill={color} />
                    </BarChart>
                );
            default:
                return (
                    <LineChart {...commonProps}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="time" />
                        <YAxis />
                        <Tooltip
                            formatter={(value: number) => [
                                formatValue(value),
                                title,
                            ]}
                            labelFormatter={(label) => `時間: ${label}`}
                        />
                        <Line
                            type="monotone"
                            dataKey="value"
                            stroke={color}
                            strokeWidth={2}
                            dot={{ fill: color, strokeWidth: 2, r: 4 }}
                            activeDot={{ r: 6, stroke: color, strokeWidth: 2 }}
                        />
                    </LineChart>
                );
        }
    };

    if (!data || data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg bg-gray-50">
                <div className="text-center">
                    <div className="mb-2 text-4xl text-gray-400">📊</div>
                    <p className="text-gray-500">暫無資料</p>
                    <p className="mt-1 text-sm text-gray-400">
                        等待效能資料載入...
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                <div className="flex items-center space-x-2">
                    <div
                        className="h-3 w-3 rounded-full"
                        style={{ backgroundColor: color }}
                    ></div>
                    <span className="text-sm text-gray-500">
                        {chartData.length} 個資料點
                    </span>
                </div>
            </div>
            <div style={{ height }}>
                <ResponsiveContainer width="100%" height="100%">
                    {renderChart()}
                </ResponsiveContainer>
            </div>
        </div>
    );
};

// 多指標圖表組件
interface MultiMetricChartProps {
    data: PerformanceData[];
    metrics: Array<{
        key: keyof PerformanceData;
        title: string;
        color: string;
    }>;
    type?: 'line' | 'area';
    height?: number;
}

export const MultiMetricChart: React.FC<MultiMetricChartProps> = ({
    data,
    metrics,
    type = 'line',
    height = 300,
}) => {
    const formatTime = (timestamp: number): string => {
        return new Date(timestamp).toLocaleTimeString('zh-TW', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const chartData = data.map((item) => ({
        time: formatTime(item.timestamp),
        timestamp: item.timestamp,
        ...metrics.reduce((acc, metric) => {
            acc[metric.key] = item[metric.key];
            return acc;
        }, {} as any),
    }));

    const renderChart = () => {
        const commonProps = {
            data: chartData,
            margin: { top: 5, right: 30, left: 20, bottom: 5 },
        };

        if (type === 'area') {
            return (
                <AreaChart {...commonProps}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="time" />
                    <YAxis />
                    <Tooltip
                        formatter={(value: number, name: string) => [
                            value.toFixed(2),
                            metrics.find((m) => m.key === name)?.title || name,
                        ]}
                        labelFormatter={(label) => `時間: ${label}`}
                    />
                    {metrics.map((metric, index) => (
                        <Area
                            key={metric.key}
                            type="monotone"
                            dataKey={metric.key}
                            stackId="1"
                            stroke={metric.color}
                            fill={metric.color}
                            fillOpacity={0.6}
                        />
                    ))}
                </AreaChart>
            );
        }

        return (
            <LineChart {...commonProps}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="time" />
                <YAxis />
                <Tooltip
                    formatter={(value: number, name: string) => [
                        value.toFixed(2),
                        metrics.find((m) => m.key === name)?.title || name,
                    ]}
                    labelFormatter={(label) => `時間: ${label}`}
                />
                {metrics.map((metric) => (
                    <Line
                        key={metric.key}
                        type="monotone"
                        dataKey={metric.key}
                        stroke={metric.color}
                        strokeWidth={2}
                        dot={{ fill: metric.color, strokeWidth: 2, r: 4 }}
                        activeDot={{
                            r: 6,
                            stroke: metric.color,
                            strokeWidth: 2,
                        }}
                    />
                ))}
            </LineChart>
        );
    };

    if (!data || data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg bg-gray-50">
                <div className="text-center">
                    <div className="mb-2 text-4xl text-gray-400">📊</div>
                    <p className="text-gray-500">暫無資料</p>
                    <p className="mt-1 text-sm text-gray-400">
                        等待效能資料載入...
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">
                    效能趨勢
                </h3>
                <div className="flex items-center space-x-4">
                    {metrics.map((metric) => (
                        <div
                            key={metric.key}
                            className="flex items-center space-x-2"
                        >
                            <div
                                className="h-3 w-3 rounded-full"
                                style={{ backgroundColor: metric.color }}
                            ></div>
                            <span className="text-sm text-gray-500">
                                {metric.title}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
            <div style={{ height }}>
                <ResponsiveContainer width="100%" height="100%">
                    {renderChart()}
                </ResponsiveContainer>
            </div>
        </div>
    );
};
