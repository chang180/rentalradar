import { useState, useEffect, useCallback, useRef } from 'react';
import { AIMapService } from '../services/ai-map-service';

interface MapPoint {
    lat: number;
    lng: number;
    price?: number;
    weight?: number;
}

interface Property {
    id: number;
    position: {
        lat: number;
        lng: number;
    };
    info: {
        district: string;
        building_type: string;
        area: number;
        rent_per_month: number;
        total_rent: number;
    };
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
    const [displayMode, setDisplayMode] = useState<'properties' | 'clusters' | 'heatmap'>('properties');

    const previousViewportRef = useRef<Viewport | null>(null);
    const currentViewportRef = useRef<Viewport | null>(null);

    // 載入地圖資料
    const loadMapData = useCallback(async (viewport: Viewport, district?: string) => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();
            params.append('north', viewport.north.toString());
            params.append('south', viewport.south.toString());
            params.append('east', viewport.east.toString());
            params.append('west', viewport.west.toString());
            params.append('zoom', viewport.zoom.toString());

            if (district) {
                params.append('district', district);
            }

            const response = await fetch(`/api/map/optimized-data?${params}`);
            const data = await response.json();

            if (data.success) {
                if (data.data_type === 'clusters') {
                    setClusters(data.data);
                    setDisplayMode('clusters');
                } else {
                    setProperties(data.data || []);
                    setDisplayMode('properties');

                    // 如果啟用聚合且資料點太多，進行客戶端聚合
                    if (enableClustering && (data.data || []).length > clusterThreshold) {
                        const points: MapPoint[] = (data.data || []).map((prop: Property) => ({
                            lat: prop.position.lat,
                            lng: prop.position.lng,
                            price: prop.info.total_rent,
                        }));

                        const clientClusters = AIMapService.clusterPoints(points, 15);
                        setClusters(clientClusters);
                        setDisplayMode('clusters');
                    }
                }

                // 生成熱力圖資料
                if (enableHeatmap) {
                    const points: MapPoint[] = (data.data || []).map((prop: Property) => ({
                        lat: prop.position.lat,
                        lng: prop.position.lng,
                        price: prop.info.total_rent,
                    }));

                    const heatmap = AIMapService.generateHeatmapData(points);
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
    }, [enableClustering, enableHeatmap, clusterThreshold]);

    // 優化的視口更新
    const updateViewport = useCallback(async (newViewport: Viewport, district?: string) => {
        currentViewportRef.current = newViewport;

        if (autoOptimize) {
            const optimization = AIMapService.optimizeViewportUpdate(
                properties,
                newViewport,
                previousViewportRef.current
            );

            if (!optimization.needsUpdate) {
                return;
            }
        }

        await loadMapData(newViewport, district);
        previousViewportRef.current = newViewport;
    }, [loadMapData, properties, autoOptimize]);

    // 切換顯示模式
    const toggleDisplayMode = useCallback((mode: 'properties' | 'clusters' | 'heatmap') => {
        setDisplayMode(mode);
    }, []);

    // 獲取 AI 聚合
    const getAIClusters = useCallback(async (algorithm: 'kmeans' | 'grid' = 'kmeans', nClusters: number = 10) => {
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
                setClusters(data.clusters);
                setDisplayMode('clusters');
            }
        } catch (err) {
            setError('獲取聚合資料時發生錯誤');
            console.error('Clustering error:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    // 獲取 AI 熱力圖
    const getAIHeatmap = useCallback(async (resolution: 'low' | 'medium' | 'high' = 'medium') => {
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
                setHeatmapData(data.heatmap_points);
                setDisplayMode('heatmap');
            }
        } catch (err) {
            setError('獲取熱力圖資料時發生錯誤');
            console.error('Heatmap error:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    // 價格預測
    const predictPrice = useCallback(async (propertyData: {
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
    }, []);

    // 智慧過濾
    const smartFilter = useCallback((zoom: number) => {
        if (!currentViewportRef.current) return properties;

        const viewport = currentViewportRef.current;
        const points: MapPoint[] = properties.map(prop => ({
            lat: prop.position.lat,
            lng: prop.position.lng,
            price: prop.info.total_rent,
        }));

        const filtered = AIMapService.smartFilter(points, zoom, viewport);

        // 將過濾結果轉換回 Property 格式
        return properties.filter(prop =>
            filtered.some(point =>
                point.lat === prop.position.lat && point.lng === prop.position.lng
            )
        );
    }, [properties]);

    return {
        // 狀態
        properties,
        clusters,
        heatmapData,
        loading,
        error,
        displayMode,

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