import { useCallback, useRef, useState } from 'react';
import { AIMapService } from '../services/ai-map-service';

interface MapPoint {
    lat: number;
    lng: number;
    price?: number;
    weight?: number;
}

interface Property {
    id: string;
    title: string;
    price: number;
    area: number;
    location: {
        lat: number;
        lng: number;
        address: string;
    };
    property_count: number;
    avg_rent: number;
    min_rent: number;
    max_rent: number;
    elevator_ratio: number;
    management_ratio: number;
    furniture_ratio: number;
}

interface Cluster {
    id: string;
    center: { lat: number; lng: number };
    count: number;
    bounds: {
        north: number;
        south: number;
        east: number;
        west: number;
    };
}

interface Viewport {
    north: number;
    south: number;
    east: number;
    west: number;
    zoom: number;
}

interface UseAIMapOptions {
    enableClustering?: boolean;
    enableHeatmap?: boolean;
    clusterThreshold?: number;
    autoOptimize?: boolean;
}

export function useAIMap(options: UseAIMapOptions = {}) {
    const {
        enableClustering = true,
        enableHeatmap = false,
        clusterThreshold = 100,
        autoOptimize = true,
    } = options;

    const [properties, setProperties] = useState<Property[]>([]);
    const [clusters, setClusters] = useState<Cluster[]>([]);
    const [heatmapData, setHeatmapData] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [displayMode, setDisplayMode] = useState<
        'properties' | 'clusters' | 'heatmap'
    >('properties');
    const [statistics, setStatistics] = useState<any>(null);

    const previousViewportRef = useRef<Viewport | null>(null);
    const currentViewportRef = useRef<Viewport | null>(null);

    // 載入地圖資料
    const loadMapData = useCallback(
        async (viewport: Viewport, district?: string) => {
            setLoading(true);
            setError(null);

            try {
                const params = new URLSearchParams();

                // 如果選擇了特定行政區，不傳送視口範圍參數，讓後端返回該行政區的所有資料
                if (district) {
                    params.append('district', district);
                    params.append('zoom', viewport.zoom.toString());
                } else {
                    // 只有在沒有選擇行政區時才傳送視口範圍
                    params.append('north', viewport.north.toString());
                    params.append('south', viewport.south.toString());
                    params.append('east', viewport.east.toString());
                    params.append('west', viewport.west.toString());
                    params.append('zoom', viewport.zoom.toString());
                }

                const response = await fetch(
                    `/api/map/optimized-data?${params}`,
                );
                const data = await response.json();

                if (data.success) {
                    // 處理聚合資料結構
                    const propertiesData: Property[] = data.data.rentals || [];
                    setProperties(propertiesData);
                    setDisplayMode('properties');

                    // 設定統計資料
                    if (data.data.statistics) {
                        setStatistics(data.data.statistics);
                    }

                    // 生成熱力圖資料
                    if (enableHeatmap && propertiesData.length > 0) {
                        const points: MapPoint[] = propertiesData.map(
                            (prop: Property) => ({
                                lat: prop.location.lat,
                                lng: prop.location.lng,
                                price: prop.price,
                            }),
                        );

                        const heatmap =
                            AIMapService.generateHeatmapData(points);
                        setHeatmapData(heatmap);
                    }
                } else {
                    setError('無法載入地圖資料');
                }
            } catch (err) {
                setError('載入資料時發生錯誤');
                console.error('Map data loading error:', err);
            } finally {
                setLoading(false);
            }
        },
        [enableClustering, enableHeatmap, clusterThreshold],
    );

    // 優化的視口更新
    const updateViewport = useCallback(
        async (newViewport: Viewport, district?: string) => {
            currentViewportRef.current = newViewport;

            if (autoOptimize) {
                const optimization = AIMapService.optimizeViewportUpdate(
                    properties,
                    newViewport,
                    previousViewportRef.current,
                );

                if (!optimization.needsUpdate) {
                    return;
                }
            }

            await loadMapData(newViewport, district);
            previousViewportRef.current = newViewport;
        },
        [loadMapData, properties, autoOptimize],
    );

    // 切換顯示模式
    const toggleDisplayMode = useCallback(
        (mode: 'properties' | 'clusters' | 'heatmap') => {
            setDisplayMode(mode);
        },
        [],
    );

    // 獲取 AI 聚合
    const getAIClusters = useCallback(
        async (
            algorithm: 'kmeans' | 'grid' = 'kmeans',
            nClusters: number = 10,
        ) => {
            if (!currentViewportRef.current) return;

            setLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('algorithm', algorithm);
                params.append('clusters', nClusters.toString());

                const viewport = currentViewportRef.current;
                params.append('north', viewport.north.toString());
                params.append('south', viewport.south.toString());
                params.append('east', viewport.east.toString());
                params.append('west', viewport.west.toString());

                const response = await fetch(`/api/map/clusters?${params}`);
                const data = await response.json();

                if (data.success) {
                    setClusters(data.data.clusters || []);
                    setDisplayMode('clusters');
                }
            } catch (err) {
                setError('獲取聚合資料時發生錯誤');
                console.error('Clustering error:', err);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    // 獲取 AI 熱力圖
    const getAIHeatmap = useCallback(
        async (resolution: 'low' | 'medium' | 'high' = 'medium') => {
            if (!currentViewportRef.current) return;

            setLoading(true);
            try {
                const params = new URLSearchParams();
                params.append('resolution', resolution);

                const viewport = currentViewportRef.current;
                params.append('north', viewport.north.toString());
                params.append('south', viewport.south.toString());
                params.append('east', viewport.east.toString());
                params.append('west', viewport.west.toString());

                const response = await fetch(`/api/map/ai-heatmap?${params}`);
                const data = await response.json();

                if (data.success) {
                    setHeatmapData(data.data.heatmap_points || []);
                    setDisplayMode('heatmap');
                }
            } catch (err) {
                setError('獲取熱力圖資料時發生錯誤');
                console.error('Heatmap error:', err);
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    // 價格預測
    const predictPrice = useCallback(
        async (propertyData: {
            lat: number;
            lng: number;
            area?: number;
            floor?: number;
            age?: number;
        }) => {
            try {
                const response = await fetch('/api/map/predict-prices', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        properties: [propertyData],
                    }),
                });

                const data = await response.json();
                return data.success ? data.predictions[0] : null;
            } catch (err) {
                console.error('Price prediction error:', err);
                return null;
            }
        },
        [],
    );

    // 智慧過濾
    const smartFilter = useCallback(
        (zoom: number) => {
            if (!currentViewportRef.current) return properties;

            const viewport = currentViewportRef.current;
            const points: MapPoint[] = properties.map((prop) => ({
                lat: prop.position.lat,
                lng: prop.position.lng,
                price: prop.info.total_rent,
            }));

            const filtered = AIMapService.smartFilter(points, zoom, viewport);

            // 將過濾結果轉換回 Property 格式
            return properties.filter((prop) =>
                filtered.some(
                    (point) =>
                        point.lat === prop.position.lat &&
                        point.lng === prop.position.lng,
                ),
            );
        },
        [properties],
    );

    return {
        // 狀態
        properties,
        clusters,
        heatmapData,
        loading,
        error,
        displayMode,
        statistics,

        // 方法
        updateViewport,
        toggleDisplayMode,
        getAIClusters,
        getAIHeatmap,
        predictPrice,
        smartFilter,

        // 輔助方法
        setDisplayMode,
        setError,
    };
}
