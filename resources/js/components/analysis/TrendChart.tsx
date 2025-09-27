import React from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { MarketTrendPoint } from '@/types/analysis';

interface TrendChartProps {
    data: MarketTrendPoint[];
}

const currencyFormatter = (value: number | null | undefined) => {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    return `$${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
};

export function TrendChart({ data }: TrendChartProps) {
    if (!data || data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">Add more rental records to unlock trend analysis.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Market Trend</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Average rent, median rent, and 3-period moving average for the selected range.
                    </p>
                </div>
            </div>
            <div className="h-72 w-full">
                <ResponsiveContainer>
                    <AreaChart data={data} margin={{ top: 20, right: 16, bottom: 8, left: 0 }}>
                        <defs>
                            <linearGradient id="trendAverage" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.25} />
                                <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="4 4" stroke="var(--grid-color, #e5e7eb)" />
                        <XAxis
                            dataKey="period"
                            tickLine={false}
                            axisLine={false}
                            tick={{ fill: 'var(--axis-color, #6b7280)', fontSize: 12 }}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`}
                            tick={{ fill: 'var(--axis-color, #6b7280)', fontSize: 12 }}
                        />
                        <Tooltip
                            contentStyle={{
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                borderRadius: '0.5rem',
                                border: 'none',
                                color: 'white',
                                fontSize: '0.75rem',
                            }}
                            itemStyle={{ color: 'white' }}
                            formatter={(value, key) => {
                                if (key === 'volume') {
                                    return [String(value), 'Listings'];
                                }

                                return [currencyFormatter(Number(value)), key === 'moving_average' ? 'Moving Average' : 'Average Rent'];
                            }}
                        />
                        <Area
                            type="monotone"
                            dataKey="average_rent"
                            stroke="#3b82f6"
                            fill="url(#trendAverage)"
                            strokeWidth={2}
                            name="Average Rent"
                        />
                        <Line
                            type="monotone"
                            dataKey="moving_average"
                            stroke="#0ea5e9"
                            strokeWidth={2}
                            dot={false}
                            name="Moving Average"
                        />
                        <Line
                            type="monotone"
                            dataKey="median_rent"
                            stroke="#f97316"
                            strokeWidth={2}
                            dot={false}
                            name="Median Rent"
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export default TrendChart;
