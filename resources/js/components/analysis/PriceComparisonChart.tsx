import type { PriceComparisonDistrict } from '@/types/analysis';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface PriceComparisonChartProps {
    data: PriceComparisonDistrict[];
}

export function PriceComparisonChart({ data }: PriceComparisonChartProps) {
    if (!data || data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    比較資料尚未提供。
                </p>
            </div>
        );
    }

    const topTen = [...data]
        .sort((a, b) => b.average_rent - a.average_rent)
        .slice(0, 10);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        各行政區平均租金
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        按平均月租金排序的頂尖行政區。
                    </p>
                </div>
            </div>
            <div className="h-72 w-full">
                <ResponsiveContainer>
                    <BarChart
                        data={topTen}
                        layout="vertical"
                        margin={{ top: 16, right: 24, bottom: 0, left: 80 }}
                    >
                        <CartesianGrid strokeDasharray="4 4" />
                        <XAxis
                            type="number"
                            tickFormatter={(value) =>
                                `$${(value / 1000).toFixed(0)}k`
                            }
                        />
                        <YAxis
                            dataKey="district"
                            type="category"
                            width={120}
                            tick={{ fontSize: 12 }}
                        />
                        <Tooltip
                            cursor={{ fill: 'rgba(59, 130, 246, 0.08)' }}
                            formatter={(value, key) => {
                                if (key === 'price_per_ping') {
                                    const label = value
                                        ? `$${Number(value).toFixed(0)} / 坪`
                                        : 'N/A';
                                    return [label, '每坪價格'];
                                }

                                return [
                                    `$${Number(value).toLocaleString()}`,
                                    key === 'average_rent'
                                        ? '平均租金'
                                        : '中位數租金',
                                ];
                            }}
                        />
                        <Bar
                            dataKey="average_rent"
                            fill="#6366f1"
                            radius={[4, 4, 4, 4]}
                            name="平均租金"
                        />
                        <Bar
                            dataKey="median_rent"
                            fill="#22c55e"
                            radius={[4, 4, 4, 4]}
                            name="中位數租金"
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export default PriceComparisonChart;
