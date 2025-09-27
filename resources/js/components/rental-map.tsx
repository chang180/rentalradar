import React, { useEffect, useState, useRef, useCallback, useMemo, memo } from 'react';
import { MapContainer, TileLayer, Marker, Popup, CircleMarker, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useAIMap } from '../hooks/use-ai-map';
import { LoadingIndicator } from './LoadingIndicator';
import { PerformanceMonitor } from './PerformanceMonitor';

// 修正 Leaflet 預設圖標問題
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

interface Property {
    id: number;
    position: {
        lat: number;
        lng: number;
    };
    info: {
        district: string;
        village: string;
        road: string;
        building_type: string;
        total_floor_area: number;
        rent_per_month: number;
        total_rent: number;
        rent_date: string;
        address: string;
        pattern: string;
    };
}

interface MapData {
    success: boolean;
    data: Property[];
    count: number;
}

// 防抖動處理
const debounce = (func: Function, wait: number) => {
    let timeout: NodeJS.Timeout;
    return function executedFunction(...args: any[]) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// 節流處理
const throttle = (func: Function, limit: number) => {
    let inThrottle: boolean;
    return function(...args: any[]) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

// 優化的地圖事件處理組件
const MapEventHandler = memo(({ onViewportChange }: { onViewportChange: (viewport: any) => void }) => {
    const debouncedViewportChange = useMemo(
        () => debounce((bounds: any, zoom: number) => {
            onViewportChange({
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
                zoom,
            });
        }, 100),
        [onViewportChange]
    );

    const map = useMapEvents({
        moveend: () => {
            const bounds = map.getBounds();
            const zoom = map.getZoom();
            debouncedViewportChange(bounds, zoom);
        },
        zoomend: () => {
            const bounds = map.getBounds();
            const zoom = map.getZoom();
            debouncedViewportChange(bounds, zoom);
        },
    });

    return null;
});

// 性能監控接口
interface PerformanceMetrics {
    loadTime: number;
    renderTime: number;
    memoryUsage: number;
    markerCount: number;
}

// 優化的圖標緩存
const iconCache = new Map<string, L.DivIcon>();

const RentalMap = memo(() => {
    const [selectedDistrict, setSelectedDistrict] = useState<string>('');
    const [districts, setDistricts] = useState<{ district: string; property_count: number }[]>([]);
    const [viewMode, setViewMode] = useState<'properties' | 'clusters' | 'heatmap'>('properties');
    const [aiMode, setAIMode] = useState<'off' | 'clustering' | 'heatmap'>('off');
    const [performanceMetrics, setPerformanceMetrics] = useState<PerformanceMetrics | null>(null);
    const [isInitialLoad, setIsInitialLoad] = useState(true);

    const mapRef = useRef<L.Map | null>(null);
    const loadStartTime = useRef<number>(0);

    // 使用 AI 地圖 Hook
    const {
        properties,
        clusters,
        heatmapData,
        loading,
        error,
        displayMode,
        updateViewport,
        toggleDisplayMode,
        getAIClusters,
        getAIHeatmap,
        predictPrice,
    } = useAIMap({
        enableClustering: true,
        enableHeatmap: true,
        clusterThreshold: 50,
        autoOptimize: true,
    });

    // 台北市中心座標
    const defaultCenter: [number, number] = [25.0330, 121.5654];
    const defaultZoom = 12;

    // 性能監控
    const updatePerformanceMetrics = useCallback(() => {
        if (typeof performance !== 'undefined') {
            const now = performance.now();
            const memoryInfo = (performance as any).memory;

            setPerformanceMetrics({
                loadTime: loadStartTime.current > 0 ? now - loadStartTime.current : 0,
                renderTime: now,
                memoryUsage: memoryInfo ? Math.round(memoryInfo.usedJSHeapSize / 1024 / 1024) : 0,
                markerCount: properties.length + clusters.length,
            });
        }
    }, [properties.length, clusters.length]);

    useEffect(() => {
        loadStartTime.current = performance.now();
        fetchDistricts();

        // 延遲初始載入以改善首次渲染性能
        const timer = setTimeout(() => {
            updateViewport({
                north: 25.2,
                south: 24.9,
                east: 121.8,
                west: 121.3,
                zoom: defaultZoom,
            }, selectedDistrict);
            setIsInitialLoad(false);
        }, 100);

        return () => clearTimeout(timer);
    }, []);

    useEffect(() => {
        updatePerformanceMetrics();
    }, [properties, clusters, heatmapData, updatePerformanceMetrics]);

    useEffect(() => {
        if (mapRef.current) {
            const bounds = mapRef.current.getBounds();
            const zoom = mapRef.current.getZoom();
            updateViewport({
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
                zoom,
            }, selectedDistrict);
        }
    }, [selectedDistrict]);

    const fetchDistricts = async () => {
        try {
            const response = await fetch('/api/map/districts');
            const data = await response.json();
            if (data.success) {
                setDistricts(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch districts:', err);
        }
    };

    // 優化的視口變更處理，加入節流
    const handleViewportChange = useCallback(
        throttle((viewport: any) => {
            updateViewport(viewport, selectedDistrict);
        }, 16), // 60fps
        [updateViewport, selectedDistrict]
    );

    // 優化的圖標創建，使用緩存
    const createCustomIcon = useCallback((rentPerMonth: number) => {
        const priceCategory = rentPerMonth > 1000 ? 'high' :
                            rentPerMonth > 600 ? 'medium' : 'low';

        if (iconCache.has(priceCategory)) {
            return iconCache.get(priceCategory)!;
        }

        const color = priceCategory === 'high' ? '#ef4444' :
                     priceCategory === 'medium' ? '#f97316' : '#22c55e';

        const icon = L.divIcon({
            html: `<div class="marker-${priceCategory}" style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            className: 'custom-marker',
            iconSize: [12, 12],
            iconAnchor: [6, 6],
        });

        iconCache.set(priceCategory, icon);
        return icon;
    }, []);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('zh-TW', {
            style: 'currency',
            currency: 'TWD',
            minimumFractionDigits: 0,
        }).format(amount);
    };

    if (loading && isInitialLoad) {
        return (
            <div className="h-full flex items-center justify-center">
                <LoadingIndicator
                    size="lg"
                    text="載入地圖資料中..."
                    showProgress={true}
                />
            </div>
        );
    }

    if (error) {
        return (
            <div className="h-full flex items-center justify-center">
                <div className="text-center">
                    <p className="text-red-600 dark:text-red-400">{error}</p>
                    <button
                        onClick={() => fetchProperties(selectedDistrict)}
                        className="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        重新載入
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="h-full flex flex-col">
            {/* AI 控制面板 */}
            <div className="flex-shrink-0 flex flex-wrap items-center gap-4 p-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center gap-2">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        行政區：
                    </label>
                    <select
                        value={selectedDistrict}
                        onChange={(e) => setSelectedDistrict(e.target.value)}
                        className="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    >
                        <option value="">全部區域</option>
                        {districts.map((district) => (
                            <option key={district.district} value={district.district}>
                                {district.district} ({district.property_count})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        顯示模式：
                    </label>
                    <select
                        value={displayMode}
                        onChange={(e) => toggleDisplayMode(e.target.value as any)}
                        className="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                    >
                        <option value="properties">個別物件</option>
                        <option value="clusters">AI 聚合</option>
                        <option value="heatmap">熱力圖</option>
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={() => getAIClusters('kmeans', 15)}
                        disabled={loading}
                        className="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 text-sm"
                    >
                        AI 聚合
                    </button>
                    <button
                        onClick={() => getAIHeatmap('medium')}
                        disabled={loading}
                        className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 text-sm"
                    >
                        AI 熱力圖
                    </button>
                </div>

                <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <div className="flex items-center gap-1">
                        <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span>低租金</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="w-3 h-3 bg-orange-500 rounded-full"></div>
                        <span>中租金</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="w-3 h-3 bg-red-500 rounded-full"></div>
                        <span>高租金</span>
                    </div>
                </div>

                <div className="ml-auto text-sm text-gray-600 dark:text-gray-400">
                    {displayMode === 'properties' && `顯示 ${properties.length} 個物件`}
                    {displayMode === 'clusters' && `顯示 ${clusters.length} 個聚合`}
                    {displayMode === 'heatmap' && `熱力圖模式`}
                    {loading && ' (載入中...)'}
                </div>
            </div>

            {/* 地圖容器 */}
            <div className="flex-1 relative" style={{ minHeight: '400px' }}>
                <MapContainer
                    center={defaultCenter}
                    zoom={defaultZoom}
                    style={{ height: '100%', width: '100%', minHeight: '400px' }}
                    ref={mapRef}
                    className="w-full h-full"
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />

                    <MapEventHandler onViewportChange={handleViewportChange} />

                    {/* 個別物件標記 */}
                    {displayMode === 'properties' && properties.map((property) => (
                        <Marker
                            key={property.id}
                            position={[property.position.lat, property.position.lng]}
                            icon={createCustomIcon(property.info.rent_per_month)}
                        >
                            <Popup className="rental-popup">
                                <div className="p-2 min-w-64">
                                    <h3 className="font-semibold text-gray-900 mb-2">
                                        {property.info.district} - {property.info.building_type}
                                    </h3>

                                    <div className="space-y-1 text-sm text-gray-600">
                                        <div className="flex justify-between">
                                            <span>地址：</span>
                                            <span>{property.info.district}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>類型：</span>
                                            <span>{property.info.building_type}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>面積：</span>
                                            <span>{property.info.area} m²</span>
                                        </div>
                                        <div className="flex justify-between font-medium text-blue-600">
                                            <span>租金：</span>
                                            <span>
                                                {formatCurrency(property.info.rent_per_month)}/m²
                                            </span>
                                        </div>
                                        <div className="flex justify-between font-medium text-green-600">
                                            <span>總租金：</span>
                                            <span>{formatCurrency(property.info.total_rent)}</span>
                                        </div>
                                    </div>
                                </div>
                            </Popup>
                        </Marker>
                    ))}

                    {/* AI 聚合標記 - 增強視覺效果 */}
                    {displayMode === 'clusters' && clusters.map((cluster) => {
                        // 使用視覺等級來確定大小和顏色
                        const visualLevel = cluster.visual_level || Math.min(5, Math.max(1, Math.floor(cluster.count / 10) + 1));
                        const baseSize = 15;
                        const size = Math.min(Math.max(baseSize + (visualLevel * 6), 15), 70);

                        // 基於價格統計選擇顏色
                        const priceStats = cluster.price_stats;
                        let color = '#22c55e'; // 預設綠色
                        let borderColor = '#16a34a';

                        if (priceStats && priceStats.avg > 0) {
                            if (priceStats.avg >= 40000) {
                                color = '#dc2626'; // 高價紅色
                                borderColor = '#991b1b';
                            } else if (priceStats.avg >= 25000) {
                                color = '#f97316'; // 中價橙色
                                borderColor = '#ea580c';
                            } else {
                                color = '#eab308'; // 低價黃色
                                borderColor = '#ca8a04';
                            }
                        } else if (cluster.count > 50) {
                            color = '#6366f1'; // 大量群集藍色
                            borderColor = '#4f46e5';
                        }

                        return (
                            <CircleMarker
                                key={cluster.id}
                                center={[cluster.center.lat, cluster.center.lng]}
                                radius={size / 3}
                                pathOptions={{
                                    fillColor: color,
                                    color: borderColor,
                                    weight: 2,
                                    opacity: 0.9,
                                    fillOpacity: 0.7,
                                }}
                            >
                                <Popup className="enhanced-cluster-popup">
                                    <div className="p-3 min-w-72">
                                        <div className="flex items-center justify-between mb-3">
                                            <h3 className="font-semibold text-gray-900">
                                                AI 聚合區域
                                            </h3>
                                            <span className={`px-2 py-1 rounded text-xs font-medium text-white`}
                                                  style={{ backgroundColor: color }}>
                                                等級 {visualLevel}
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div className="space-y-1">
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">物件數量：</span>
                                                    <span className="font-medium">{cluster.count}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">覆蓋半徑：</span>
                                                    <span className="font-medium">{cluster.radius_km?.toFixed(2)} km</span>
                                                </div>
                                                {cluster.density && (
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">密度：</span>
                                                        <span className="font-medium">{cluster.density.toFixed(1)}/km²</span>
                                                    </div>
                                                )}
                                            </div>

                                            {priceStats && (
                                                <div className="space-y-1">
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">平均租金：</span>
                                                        <span className="font-medium text-blue-600">
                                                            {formatCurrency(priceStats.avg)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">租金範圍：</span>
                                                        <span className="font-medium text-green-600 text-xs">
                                                            {formatCurrency(priceStats.min)} - {formatCurrency(priceStats.max)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">中位數：</span>
                                                        <span className="font-medium">{formatCurrency(priceStats.median)}</span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* 價格分佈視覺化 */}
                                        {priceStats && (
                                            <div className="mt-3 pt-3 border-t border-gray-200">
                                                <div className="text-xs text-gray-600 mb-1">價格分佈</div>
                                                <div className="flex h-2 bg-gray-200 rounded overflow-hidden">
                                                    <div
                                                        className="bg-green-500"
                                                        style={{ width: '33.33%' }}
                                                        title={`低價: ${formatCurrency(priceStats.min)}`}
                                                    />
                                                    <div
                                                        className="bg-yellow-500"
                                                        style={{ width: '33.33%' }}
                                                        title={`中價: ${formatCurrency(priceStats.median)}`}
                                                    />
                                                    <div
                                                        className="bg-red-500"
                                                        style={{ width: '33.34%' }}
                                                        title={`高價: ${formatCurrency(priceStats.max)}`}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </Popup>
                            </CircleMarker>
                        );
                    })}

                    {/* 熱力圖點 - 增強視覺效果 */}
                    {displayMode === 'heatmap' && heatmapData.map((point, index) => {
                        // 使用進階顏色和大小計算
                        const radius = point.radius || Math.max(3, Math.min(15, 5 + (point.weight * 8)));
                        const color = point.color || (
                            point.weight > 0.7 ? 'rgba(255, 0, 0, 0.8)' :
                            point.weight > 0.4 ? 'rgba(255, 255, 0, 0.7)' : 'rgba(0, 255, 0, 0.6)'
                        );
                        const borderColor = point.weight > 0.7 ? '#dc2626' :
                                           point.weight > 0.4 ? '#ca8a04' : '#16a34a';

                        return (
                            <CircleMarker
                                key={`heatmap-${index}`}
                                center={[point.lat, point.lng]}
                                radius={radius}
                                pathOptions={{
                                    fillColor: color,
                                    color: borderColor,
                                    weight: 1,
                                    opacity: 0.8,
                                    fillOpacity: point.intensity || point.weight,
                                }}
                            >
                                <Popup>
                                    <div className="p-2 min-w-48">
                                        <h4 className="font-semibold text-gray-900 mb-2">
                                            熱力圖區域
                                        </h4>
                                        <div className="space-y-1 text-sm">
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">權重：</span>
                                                <span className="font-medium">{(point.weight * 100).toFixed(1)}%</span>
                                            </div>
                                            {point.count && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">資料點：</span>
                                                    <span className="font-medium">{point.count}</span>
                                                </div>
                                            )}
                                            {point.avg_price && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">平均租金：</span>
                                                    <span className="font-medium text-blue-600">
                                                        {formatCurrency(point.avg_price)}
                                                    </span>
                                                </div>
                                            )}
                                            {point.price_range && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">價格區間：</span>
                                                    <span className="font-medium text-green-600">
                                                        {point.price_range}
                                                    </span>
                                                </div>
                                            )}
                                            {point.intensity && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">強度：</span>
                                                    <span className="font-medium">{(point.intensity * 100).toFixed(1)}%</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </Popup>
                            </CircleMarker>
                        );
                    })}
                </MapContainer>
            </div>
        </div>
    );
});

export default RentalMap;