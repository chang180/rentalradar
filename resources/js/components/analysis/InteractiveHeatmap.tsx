import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer,
    ScatterChart,
    Scatter,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Cell,
} from 'recharts';
import type { MultiDimensionalAnalysis } from '@/types/analysis';

interface InteractiveHeatmapProps {
    data: MultiDimensionalAnalysis['spatial'];
    onDistrictSelect?: (district: string) => void;
    selectedDistrict?: string;
}

interface HeatmapPoint {
    x: number;
    y: number;
    value: number;
    district: string;
    listings: number;
    average_rent: number;
    median_rent: number;
    price_per_ping: number | null;
}

export function InteractiveHeatmap({ 
    data, 
    onDistrictSelect, 
    selectedDistrict 
}: InteractiveHeatmapProps) {
    const [hoveredPoint, setHoveredPoint] = useState<HeatmapPoint | null>(null);

    const heatmapData = useMemo(() => {
        if (!data || data.length === 0) return [];

        // 計算價格和交易量的標準化值用於散點圖
        const rents = data.map(d => d.average_rent).filter(r => r > 0);
        const listings = data.map(d => d.listings).filter(l => l > 0);
        
        const minRent = Math.min(...rents);
        const maxRent = Math.max(...rents);
        const minListings = Math.min(...listings);
        const maxListings = Math.max(...listings);

        return data.map((item, index) => ({
            x: ((item.average_rent - minRent) / (maxRent - minRent)) * 100,
            y: ((item.listings - minListings) / (maxListings - minListings)) * 100,
            value: item.average_rent,
            district: item.district,
            listings: item.listings,
            average_rent: item.average_rent,
            median_rent: item.median_rent,
            price_per_ping: item.price_per_ping,
            // 計算熱點分數 (價格 * 交易量權重)
            heatScore: item.average_rent * Math.log(item.listings + 1),
        }));
    }, [data]);

    const getColorIntensity = (point: HeatmapPoint) => {
        const maxHeat = Math.max(...heatmapData.map(p => p.heatScore));
        const intensity = point.heatScore / maxHeat;
        
        if (intensity > 0.8) return '#ef4444'; // 紅色 - 高熱點
        if (intensity > 0.6) return '#f97316'; // 橙色
        if (intensity > 0.4) return '#eab308'; // 黃色
        if (intensity > 0.2) return '#22c55e'; // 綠色
        return '#6b7280'; // 灰色 - 低熱點
    };

    const CustomTooltip = ({ active, payload }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <h4 className="font-semibold text-gray-900 dark:text-white">{data.district}</h4>
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
                            <span className="text-gray-500 dark:text-gray-400">物件數量:</span>
                            <span className="font-medium">{data.listings}</span>
                        </div>
                        {data.price_per_ping && (
                            <div className="flex justify-between gap-4">
                                <span className="text-gray-500 dark:text-gray-400">每坪價格:</span>
                                <span className="font-medium">${data.price_per_ping.toLocaleString()}</span>
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
                    需要更多區域資料來生成互動式熱力圖
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">互動式市場熱力圖</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        點擊區域查看詳細資訊，顏色深淺表示市場熱度
                    </p>
                </div>
                <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-red-500"></div>
                        <span>高熱點</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-yellow-500"></div>
                        <span>中熱點</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-green-500"></div>
                        <span>低熱點</span>
                    </div>
                </div>
            </div>
            
            <div className="h-80 w-full">
                <ResponsiveContainer>
                    <ScatterChart
                        data={heatmapData}
                        margin={{ top: 20, right: 20, bottom: 20, left: 20 }}
                    >
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--grid-color, #e5e7eb)" />
                        <XAxis 
                            type="number" 
                            dataKey="x" 
                            name="價格標準化"
                            tick={false}
                            axisLine={false}
                        />
                        <YAxis 
                            type="number" 
                            dataKey="y" 
                            name="交易量標準化"
                            tick={false}
                            axisLine={false}
                        />
                        <Tooltip content={<CustomTooltip />} />
                        <Scatter
                            dataKey="value"
                            onClick={(data) => onDistrictSelect?.(data.district)}
                            onMouseEnter={(data) => setHoveredPoint(data)}
                            onMouseLeave={() => setHoveredPoint(null)}
                        >
                            {heatmapData.map((entry, index) => (
                                <Cell
                                    key={`cell-${index}`}
                                    fill={getColorIntensity(entry)}
                                    stroke={selectedDistrict === entry.district ? '#3b82f6' : 'transparent'}
                                    strokeWidth={selectedDistrict === entry.district ? 2 : 0}
                                    r={Math.max(8, Math.min(20, entry.listings / 5))}
                                />
                            ))}
                        </Scatter>
                    </ScatterChart>
                </ResponsiveContainer>
            </div>

            {hoveredPoint && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-500/40 dark:bg-blue-500/10">
                    <p className="text-sm text-blue-800 dark:text-blue-200">
                        <strong>{hoveredPoint.district}</strong> - 熱點分數: {hoveredPoint.heatScore.toFixed(2)}
                    </p>
                </div>
            )}
        </div>
    );
}

export default InteractiveHeatmap;
