import type { PriceDistribution } from '@/types/analysis';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface PriceDistributionChartProps {
    data: PriceDistribution;
}

export function PriceDistributionChart({ data }: PriceDistributionChartProps) {
    if (!data || !data.segments || data.segments.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Price distribution data will appear once rentals are
                    ingested.
                </p>
            </div>
        );
    }

    const segments = data.segments.map((segment) => ({
        label: segment.label,
        count: segment.count,
    }));

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Price Distribution
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Rental inventory grouped by monthly price segment.
                    </p>
                </div>
                {data.median && (
                    <span className="text-sm text-gray-600 dark:text-gray-300">
                        Median: ${data.median.toLocaleString()}
                    </span>
                )}
            </div>
            <div className="h-64 w-full">
                <ResponsiveContainer>
                    <BarChart
                        data={segments}
                        margin={{ top: 16, right: 16, bottom: 16, left: 16 }}
                    >
                        <CartesianGrid strokeDasharray="4 4" />
                        <XAxis
                            dataKey="label"
                            tick={{ fontSize: 12 }}
                            interval={0}
                            angle={-25}
                            height={80}
                            tickLine={false}
                        />
                        <YAxis
                            allowDecimals={false}
                            tickLine={false}
                            axisLine={false}
                        />
                        <Tooltip
                            cursor={{ fill: 'rgba(34, 197, 94, 0.08)' }}
                            formatter={(value) => [String(value), 'Listings']}
                        />
                        <Bar
                            dataKey="count"
                            fill="#22c55e"
                            radius={[4, 4, 0, 0]}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export default PriceDistributionChart;
