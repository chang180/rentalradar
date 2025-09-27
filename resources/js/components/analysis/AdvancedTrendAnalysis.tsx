import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer,
    LineChart,
    Line,
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ReferenceLine,
} from 'recharts';
import type { MarketTrendPoint, MarketForecast } from '@/types/analysis';

interface AdvancedTrendAnalysisProps {
    data: MarketTrendPoint[];
    forecast: MarketForecast;
    onPeriodSelect?: (period: string) => void;
    selectedPeriod?: string;
}

interface TrendAnalysisData extends MarketTrendPoint {
    trend_direction?: 'up' | 'down' | 'neutral';
    volatility?: number;
    momentum?: number;
}

export function AdvancedTrendAnalysis({ 
    data, 
    forecast, 
    onPeriodSelect, 
    selectedPeriod 
}: AdvancedTrendAnalysisProps) {
    const [viewMode, setViewMode] = useState<'trend' | 'forecast' | 'volatility'>('trend');

    const enhancedData = useMemo(() => {
        if (!data || data.length === 0) return [];

        return data.map((point, index) => {
            const previous = index > 0 ? data[index - 1] : null;
            const change = previous ? ((point.average_rent - previous.average_rent) / previous.average_rent) * 100 : 0;
            
            // 計算波動率 (過去3期的標準差)
            const recentPeriods = data.slice(Math.max(0, index - 2), index + 1);
            const avg = recentPeriods.reduce((sum, p) => sum + p.average_rent, 0) / recentPeriods.length;
            const variance = recentPeriods.reduce((sum, p) => sum + Math.pow(p.average_rent - avg, 2), 0) / recentPeriods.length;
            const volatility = Math.sqrt(variance);

            // 計算動量 (價格變化率)
            const momentum = change;

            return {
                ...point,
                trend_direction: change > 2 ? 'up' : change < -2 ? 'down' : 'neutral',
                volatility: Math.round(volatility),
                momentum: Math.round(momentum * 100) / 100,
            } as TrendAnalysisData;
        });
    }, [data]);

    const forecastData = useMemo(() => {
        if (!forecast.values || forecast.values.length === 0) return [];

        const lastPeriod = data[data.length - 1];
        if (!lastPeriod) return [];

        return forecast.values.map((value, index) => ({
            period: `預測 ${index + 1}`,
            average_rent: value,
            median_rent: value * 0.95, // 假設中位數為平均數的95%
            volume: lastPeriod.volume,
            moving_average: value,
            isForecast: true,
        }));
    }, [forecast.values, data]);

    const combinedData = useMemo(() => {
        return [...enhancedData, ...forecastData];
    }, [enhancedData, forecastData]);

    const CustomTooltip = ({ active, payload, label }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="font-semibold text-gray-900 dark:text-white">
                        {data.isForecast ? `預測: ${label}` : label}
                    </h4>
                    <div className="mt-2 space-y-1 text-sm">
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">平均租金:</span>
                            <span className="font-medium">${data.average_rent.toLocaleString()}</span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">中位數租金:</span>
                            <span className="font-medium">${data.median_rent.toLocaleString()}</span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">交易量:</span>
                            <span className="font-medium">{data.volume}</span>
                        </div>
                        {data.volatility !== undefined && (
                            <div className="flex justify-between gap-4">
                                <span className="text-gray-500 dark:text-gray-400">波動率:</span>
                                <span className="font-medium">{data.volatility}</span>
                            </div>
                        )}
                        {data.momentum !== undefined && (
                            <div className="flex justify-between gap-4">
                                <span className="text-gray-500 dark:text-gray-400">動量:</span>
                                <span className={`font-medium ${data.momentum > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {data.momentum > 0 ? '+' : ''}{data.momentum}%
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            );
        }
        return null;
    };

    if (!data || data.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    需要更多歷史資料來進行進階趨勢分析
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">進階趨勢分析</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        包含波動率、動量和預測模型的深度分析
                    </p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => setViewMode('trend')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'trend' 
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' 
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        趨勢
                    </button>
                    <button
                        onClick={() => setViewMode('forecast')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'forecast' 
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' 
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        預測
                    </button>
                    <button
                        onClick={() => setViewMode('volatility')}
                        className={`px-3 py-1 text-xs rounded-md ${
                            viewMode === 'volatility' 
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' 
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        波動率
                    </button>
                </div>
            </div>

            <div className="h-80 w-full">
                <ResponsiveContainer>
                    {viewMode === 'trend' ? (
                        <LineChart data={enhancedData} margin={{ top: 20, right: 30, left: 20, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--grid-color, #e5e7eb)" />
                            <XAxis 
                                dataKey="period" 
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis 
                                tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`}
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend />
                            <Line
                                type="monotone"
                                dataKey="average_rent"
                                stroke="#3b82f6"
                                strokeWidth={3}
                                name="平均租金"
                                dot={{ fill: '#3b82f6', strokeWidth: 2, r: 4 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="median_rent"
                                stroke="#22c55e"
                                strokeWidth={2}
                                name="中位數租金"
                                dot={{ fill: '#22c55e', strokeWidth: 2, r: 3 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="moving_average"
                                stroke="#f59e0b"
                                strokeWidth={2}
                                strokeDasharray="5 5"
                                name="移動平均"
                                dot={false}
                            />
                        </LineChart>
                    ) : viewMode === 'forecast' ? (
                        <AreaChart data={combinedData} margin={{ top: 20, right: 30, left: 20, bottom: 5 }}>
                            <defs>
                                <linearGradient id="forecastGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3} />
                                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--grid-color, #e5e7eb)" />
                            <XAxis 
                                dataKey="period" 
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis 
                                tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`}
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend />
                            <Area
                                type="monotone"
                                dataKey="average_rent"
                                stroke="#3b82f6"
                                fill="url(#forecastGradient)"
                                name="租金預測"
                            />
                            <ReferenceLine 
                                y={enhancedData[enhancedData.length - 1]?.average_rent} 
                                stroke="#ef4444" 
                                strokeDasharray="3 3"
                                label="當前基準"
                            />
                        </AreaChart>
                    ) : (
                        <LineChart data={enhancedData} margin={{ top: 20, right: 30, left: 20, bottom: 5 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="var(--grid-color, #e5e7eb)" />
                            <XAxis 
                                dataKey="period" 
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis 
                                tick={{ fontSize: 12 }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend />
                            <Line
                                type="monotone"
                                dataKey="volatility"
                                stroke="#ef4444"
                                strokeWidth={2}
                                name="波動率"
                                dot={{ fill: '#ef4444', strokeWidth: 2, r: 4 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="momentum"
                                stroke="#22c55e"
                                strokeWidth={2}
                                name="動量 (%)"
                                dot={{ fill: '#22c55e', strokeWidth: 2, r: 4 }}
                            />
                        </LineChart>
                    )}
                </ResponsiveContainer>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-white">預測信心度</h4>
                    <p className="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {Math.round(forecast.confidence * 100)}%
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        基於 {forecast.method} 模型
                    </p>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-white">平均波動率</h4>
                    <p className="mt-1 text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {Math.round(enhancedData.reduce((sum, d) => sum + (d.volatility || 0), 0) / enhancedData.length)}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">過去期間</p>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="text-sm font-semibold text-gray-900 dark:text-white">最新動量</h4>
                    <p className={`mt-1 text-2xl font-bold ${
                        (enhancedData[enhancedData.length - 1]?.momentum || 0) > 0 
                            ? 'text-green-600 dark:text-green-400' 
                            : 'text-red-600 dark:text-red-400'
                    }`}>
                        {enhancedData[enhancedData.length - 1]?.momentum || 0}%
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">價格變化</p>
                </div>
            </div>
        </div>
    );
}

export default AdvancedTrendAnalysis;
