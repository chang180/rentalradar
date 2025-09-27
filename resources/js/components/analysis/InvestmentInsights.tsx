import type { InvestmentHotspot, InvestmentInsights } from '@/types/analysis';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    PolarAngleAxis,
    PolarGrid,
    PolarRadiusAxis,
    Radar,
    RadarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface InvestmentInsightsProps {
    data: InvestmentInsights;
    onHotspotSelect?: (hotspot: InvestmentHotspot) => void;
    selectedHotspot?: string;
}

interface RiskAssessment {
    market_risk: number;
    liquidity_risk: number;
    credit_risk: number;
    operational_risk: number;
}

interface InvestmentMetrics {
    roi: number;
    cap_rate: number;
    cash_flow: number;
    appreciation: number;
}

export function InvestmentInsightsComponent({
    data,
    onHotspotSelect,
    selectedHotspot,
}: InvestmentInsightsProps) {
    const [viewMode, setViewMode] = useState<'hotspots' | 'signals' | 'risk'>(
        'hotspots',
    );

    const riskAssessment = useMemo((): RiskAssessment => {
        // 基於市場信號計算風險評估
        const totalSignals =
            data.signals.bullish.length +
            data.signals.bearish.length +
            data.signals.neutral.length;
        const bearishRatio =
            data.signals.bearish.length / Math.max(totalSignals, 1);
        const bullishRatio =
            data.signals.bullish.length / Math.max(totalSignals, 1);

        return {
            market_risk: Math.round((1 - bullishRatio) * 100),
            liquidity_risk: Math.round((1 - data.confidence) * 100),
            credit_risk: Math.round(bearishRatio * 100),
            operational_risk: Math.round((1 - data.confidence) * 80),
        };
    }, [data]);

    const investmentMetrics = useMemo((): InvestmentMetrics => {
        // 基於熱點資料計算投資指標
        const avgScore =
            data.hotspots.reduce((sum, h) => sum + h.score, 0) /
            Math.max(data.hotspots.length, 1);
        const avgRent =
            data.hotspots.reduce((sum, h) => sum + h.average_rent, 0) /
            Math.max(data.hotspots.length, 1);

        return {
            roi: Math.round(avgScore * 15), // 假設ROI基於熱點分數
            cap_rate: Math.round(((avgRent * 12) / 1000000) * 100) / 100, // 假設房價1000萬
            cash_flow: Math.round(avgRent * 0.7), // 假設現金流為租金的70%
            appreciation: Math.round(avgScore * 8), // 假設增值潛力
        };
    }, [data]);

    const radarData = useMemo(() => {
        return [
            {
                metric: '市場風險',
                value: riskAssessment.market_risk,
                fullMark: 100,
            },
            {
                metric: '流動性風險',
                value: riskAssessment.liquidity_risk,
                fullMark: 100,
            },
            {
                metric: '信用風險',
                value: riskAssessment.credit_risk,
                fullMark: 100,
            },
            {
                metric: '營運風險',
                value: riskAssessment.operational_risk,
                fullMark: 100,
            },
        ];
    }, [riskAssessment]);

    const metricsData = useMemo(() => {
        return [
            { name: 'ROI', value: investmentMetrics.roi, color: '#3b82f6' },
            {
                name: '資本化率',
                value: investmentMetrics.cap_rate,
                color: '#22c55e',
            },
            {
                name: '現金流',
                value: investmentMetrics.cash_flow,
                color: '#f59e0b',
            },
            {
                name: '增值潛力',
                value: investmentMetrics.appreciation,
                color: '#ef4444',
            },
        ];
    }, [investmentMetrics]);

    const CustomTooltip = ({ active, payload, label }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="font-semibold text-gray-900 dark:text-white">
                        {label}
                    </h4>
                    <div className="mt-2 space-y-1 text-sm">
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">
                                分數:
                            </span>
                            <span className="font-medium">
                                {data.score.toFixed(2)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">
                                趨勢:
                            </span>
                            <span
                                className={`font-medium capitalize ${
                                    data.trend_direction === 'up'
                                        ? 'text-green-600'
                                        : data.trend_direction === 'down'
                                          ? 'text-red-600'
                                          : 'text-gray-600'
                                }`}
                            >
                                {data.trend_direction}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">
                                平均租金:
                            </span>
                            <span className="font-medium">
                                ${data.average_rent.toLocaleString()}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-gray-500 dark:text-gray-400">
                                物件數:
                            </span>
                            <span className="font-medium">{data.listings}</span>
                        </div>
                    </div>
                </div>
            );
        }
        return null;
    };

    if (!data || data.hotspots.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    需要更多市場資料來生成投資洞察
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        投資洞察分析
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        基於AI演算法的投資建議和風險評估
                    </p>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={() => setViewMode('hotspots')}
                        className={`rounded-md px-3 py-1 text-xs ${
                            viewMode === 'hotspots'
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        熱點
                    </button>
                    <button
                        onClick={() => setViewMode('signals')}
                        className={`rounded-md px-3 py-1 text-xs ${
                            viewMode === 'signals'
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        信號
                    </button>
                    <button
                        onClick={() => setViewMode('risk')}
                        className={`rounded-md px-3 py-1 text-xs ${
                            viewMode === 'risk'
                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}
                    >
                        風險
                    </button>
                </div>
            </div>

            {viewMode === 'hotspots' && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                        <h4 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                            投資熱點排名
                        </h4>
                        <div className="space-y-3">
                            {data.hotspots.map((hotspot, index) => (
                                <div
                                    key={hotspot.district}
                                    className={`cursor-pointer rounded-lg border p-4 transition-colors ${
                                        selectedHotspot === hotspot.district
                                            ? 'border-blue-200 bg-blue-50 dark:border-blue-500/40 dark:bg-blue-500/10'
                                            : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
                                    }`}
                                    onClick={() => onHotspotSelect?.(hotspot)}
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                                                {index + 1}
                                            </div>
                                            <div>
                                                <h5 className="font-semibold text-gray-900 dark:text-white">
                                                    {hotspot.district}
                                                </h5>
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    分數:{' '}
                                                    {hotspot.score.toFixed(2)} |
                                                    趨勢:{' '}
                                                    {hotspot.trend_direction}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                $
                                                {hotspot.average_rent.toLocaleString()}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {hotspot.listings} 物件
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                        <h4 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                            投資指標
                        </h4>
                        <div className="h-64">
                            <ResponsiveContainer>
                                <BarChart
                                    data={metricsData}
                                    margin={{
                                        top: 20,
                                        right: 30,
                                        left: 20,
                                        bottom: 5,
                                    }}
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        stroke="var(--grid-color, #e5e7eb)"
                                    />
                                    <XAxis
                                        dataKey="name"
                                        tick={{ fontSize: 12 }}
                                    />
                                    <YAxis tick={{ fontSize: 12 }} />
                                    <Tooltip
                                        formatter={(value, name) => [
                                            value,
                                            name,
                                        ]}
                                        labelFormatter={(label) =>
                                            `指標: ${label}`
                                        }
                                    />
                                    <Bar
                                        dataKey="value"
                                        fill="#3b82f6"
                                        radius={[4, 4, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>
            )}

            {viewMode === 'signals' && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="rounded-lg border border-green-200 bg-green-50 p-6 dark:border-green-500/40 dark:bg-green-500/10">
                        <h4 className="mb-4 text-lg font-semibold text-green-800 dark:text-green-200">
                            看漲信號
                        </h4>
                        <div className="space-y-2">
                            {data.signals.bullish.length > 0 ? (
                                data.signals.bullish.map((district, index) => (
                                    <div
                                        key={index}
                                        className="text-sm text-green-700 dark:text-green-300"
                                    >
                                        📈 {district}
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-green-600 dark:text-green-400">
                                    暫無看漲信號
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-red-200 bg-red-50 p-6 dark:border-red-500/40 dark:bg-red-500/10">
                        <h4 className="mb-4 text-lg font-semibold text-red-800 dark:text-red-200">
                            看跌信號
                        </h4>
                        <div className="space-y-2">
                            {data.signals.bearish.length > 0 ? (
                                data.signals.bearish.map((district, index) => (
                                    <div
                                        key={index}
                                        className="text-sm text-red-700 dark:text-red-300"
                                    >
                                        📉 {district}
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    暫無看跌信號
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">
                            中性信號
                        </h4>
                        <div className="space-y-2">
                            {data.signals.neutral.length > 0 ? (
                                data.signals.neutral.map((district, index) => (
                                    <div
                                        key={index}
                                        className="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        ➡️ {district}
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    暫無中性信號
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {viewMode === 'risk' && (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                        <h4 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                            風險評估雷達圖
                        </h4>
                        <div className="h-64">
                            <ResponsiveContainer>
                                <RadarChart
                                    data={radarData}
                                    margin={{
                                        top: 20,
                                        right: 30,
                                        left: 20,
                                        bottom: 20,
                                    }}
                                >
                                    <PolarGrid stroke="var(--grid-color, #e5e7eb)" />
                                    <PolarAngleAxis
                                        dataKey="metric"
                                        tick={{ fontSize: 12 }}
                                    />
                                    <PolarRadiusAxis tick={{ fontSize: 12 }} />
                                    <Radar
                                        name="風險等級"
                                        dataKey="value"
                                        stroke="#ef4444"
                                        fill="#ef4444"
                                        fillOpacity={0.3}
                                    />
                                </RadarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                            <h5 className="font-semibold text-gray-900 dark:text-white">
                                信心指數
                            </h5>
                            <div className="mt-2 flex items-center gap-2">
                                <div className="h-2 flex-1 rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        className="h-2 rounded-full bg-blue-500 transition-all duration-300"
                                        style={{
                                            width: `${data.confidence * 100}%`,
                                        }}
                                    />
                                </div>
                                <span className="text-sm font-medium text-gray-900 dark:text-white">
                                    {Math.round(data.confidence * 100)}%
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            {Object.entries(riskAssessment).map(
                                ([key, value]) => (
                                    <div
                                        key={key}
                                        className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        <h6 className="text-sm font-medium text-gray-900 capitalize dark:text-white">
                                            {key.replace('_', ' ')}
                                        </h6>
                                        <div className="mt-2 flex items-center gap-2">
                                            <div className="h-2 flex-1 rounded-full bg-gray-200 dark:bg-gray-700">
                                                <div
                                                    className={`h-2 rounded-full transition-all duration-300 ${
                                                        value > 70
                                                            ? 'bg-red-500'
                                                            : value > 40
                                                              ? 'bg-yellow-500'
                                                              : 'bg-green-500'
                                                    }`}
                                                    style={{
                                                        width: `${value}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                {value}%
                                            </span>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

export default InvestmentInsightsComponent;
