import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.fullscreen';
import 'leaflet.fullscreen/Control.FullScreen.css';
import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    CircleMarker,
    MapContainer,
    Marker,
    Popup,
    TileLayer,
    useMapEvents,
} from 'react-leaflet';
import { useAIMap } from '../hooks/use-ai-map';
import { LoadingIndicator } from './LoadingIndicator';

// 修正 Leaflet 預設圖標問題
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl:
        'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl:
        'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl:
        'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

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

interface MapData {
    success: boolean;
    data: {
        rentals: Property[];
        statistics: {
            count: number;
            cities: Record<string, number>;
            districts: Record<string, number>;
            total_properties: number;
            avg_rent_per_ping: number;
        };
    };
    meta: {
        performance: any;
        aggregation_type: string;
    };
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
    return function (this: any, ...args: any[]) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
};

// 優化的地圖事件處理組件
const MapEventHandler = memo(
    ({ onViewportChange }: { onViewportChange: (viewport: any) => void }) => {
        const debouncedViewportChange = useMemo(
            () =>
                debounce((bounds: any, zoom: number) => {
                    onViewportChange({
                        north: bounds.getNorth(),
                        south: bounds.getSouth(),
                        east: bounds.getEast(),
                        west: bounds.getWest(),
                        zoom,
                    });
                }, 100),
            [onViewportChange],
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
    },
);

// 全螢幕控制組件
const FullscreenControl = memo(() => {
    const map = useMapEvents({});
    
    useEffect(() => {
        if (map) {
            // 添加全螢幕控制
            const fullscreenControl = new (L.Control as any).FullScreen({
                position: 'topleft',
                title: {
                    'false': '進入全螢幕',
                    'true': '退出全螢幕'
                }
            });
            
            map.addControl(fullscreenControl);
            
            // 清理函數
            return () => {
                map.removeControl(fullscreenControl);
            };
        }
    }, [map]);

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
    const [selectedCity, setSelectedCity] = useState<string>('');
    const [selectedDistrict, setSelectedDistrict] = useState<string>('');
    const [cities, setCities] = useState<any[]>([]);
    const [districts, setDistricts] = useState<
        { district: string; property_count: number }[]
    >([]);
    const [viewMode, setViewMode] = useState<
        'properties' | 'clusters' | 'heatmap'
    >('properties');
    const [aiMode, setAIMode] = useState<'off' | 'clustering' | 'heatmap'>(
        'off',
    );
    const [userLocation, setUserLocation] = useState<[number, number] | null>(
        null,
    );
    const [locationError, setLocationError] = useState<string | null>(null);
    const [performanceMetrics, setPerformanceMetrics] =
        useState<PerformanceMetrics | null>(null);
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
        statistics,
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

    // 台北市中心座標（預設位置）
    const defaultCenter: [number, number] = [25.033, 121.5654];
    const defaultZoom = 11;

    // 獲取用戶位置
    const getUserLocation = useCallback(() => {
        if (!navigator.geolocation) {
            setLocationError('此瀏覽器不支援地理位置功能');
            return;
        }

        setLocationError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;
                setUserLocation([latitude, longitude]);

                // 如果沒有選擇特定行政區，移動地圖到用戶位置
                if (!selectedDistrict && mapRef.current) {
                    mapRef.current.setView([latitude, longitude], 15);
                }
            },
            (error) => {
                let errorMessage = '無法獲取位置資訊';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = '位置權限被拒絕，請允許位置存取';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = '位置資訊不可用';
                        break;
                    case error.TIMEOUT:
                        errorMessage = '位置請求超時';
                        break;
                }
                setLocationError(errorMessage);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000, // 5分鐘緩存
            },
        );
    }, [selectedDistrict]);

    // 根據行政區移動地圖
    const navigateToDistrict = useCallback(async (district: string) => {
        if (!district || !mapRef.current) return;

        try {
            // 動態獲取該行政區的實際座標範圍，包含城市參數
            const params = new URLSearchParams();
            params.append('district', district);
            if (selectedCity) {
                params.append('city', selectedCity);
            }
            
            const response = await fetch(
                `/api/map/district-bounds?${params.toString()}`,
            );
            const data = await response.json();

            if (data.success && data.bounds) {
                const { north, south, east, west } = data.bounds;
                const centerLat = (north + south) / 2;
                const centerLng = (east + west) / 2;

                // 計算合適的縮放級別
                const latDiff = north - south;
                const lngDiff = east - west;
                const maxDiff = Math.max(latDiff, lngDiff);

                let zoom = 12;
                if (maxDiff < 0.01) zoom = 14;
                else if (maxDiff < 0.02) zoom = 13;
                else if (maxDiff < 0.05) zoom = 12;
                else zoom = 11;

                mapRef.current.setView([centerLat, centerLng], zoom);
            } else {
                // 如果無法獲取邊界，使用預設座標
                const defaultCoordinates: { [key: string]: [number, number] } =
                    {
                        // 台北市
                        '台北市中正區': [25.0324, 121.5194],
                        '台北市大同區': [25.0631, 121.512],
                        '台北市中山區': [25.064, 121.525],
                        '台北市松山區': [25.05, 121.577],
                        '台北市大安區': [25.0264, 121.5435],
                        '台北市萬華區': [25.036, 121.499],
                        '台北市信義區': [25.033, 121.5654],
                        '台北市士林區': [25.088, 121.525],
                        '台北市北投區': [25.132, 121.499],
                        '台北市內湖區': [25.069, 121.594],
                        '台北市南港區': [25.054, 121.606],
                        '台北市文山區': [25.004, 121.57],
                        // 基隆市
                        '基隆市仁愛區': [25.1333, 121.7500],
                        '基隆市信義區': [25.1167, 121.7667],
                        '基隆市中正區': [25.1500, 121.7667],
                        '基隆市中山區': [25.1500, 121.7333],
                        '基隆市安樂區': [25.1167, 121.7167],
                        '基隆市暖暖區': [25.0833, 121.7500],
                        '基隆市七堵區': [25.0833, 121.7000],
                    };

                // 嘗試使用城市+行政區的組合鍵
                const cityDistrictKey = `${selectedCity}${district}`;
                const coordinates = defaultCoordinates[cityDistrictKey] || defaultCoordinates[district];
                if (coordinates) {
                    mapRef.current.setView(coordinates, 12);
                }
            }
        } catch (error) {
            console.error('Failed to get district bounds:', error);
            // 降級處理：使用預設座標
            const defaultCoordinates: { [key: string]: [number, number] } = {
                // 台北市
                '台北市中正區': [25.0324, 121.5194],
                '台北市中山區': [25.064, 121.525],
                '台北市信義區': [25.033, 121.5654],
                '台北市內湖區': [25.069, 121.594],
                '台北市北投區': [25.132, 121.499],
                '台北市士林區': [25.088, 121.525],
                '台北市大安區': [25.0264, 121.5435],
                '台北市文山區': [25.004, 121.57],
                '台北市松山區': [25.05, 121.577],
                '台北市萬華區': [25.036, 121.499],
                // 基隆市
                '基隆市仁愛區': [25.1333, 121.7500],
                '基隆市信義區': [25.1167, 121.7667],
                '基隆市中正區': [25.1500, 121.7667],
                '基隆市中山區': [25.1500, 121.7333],
                '基隆市安樂區': [25.1167, 121.7167],
                '基隆市暖暖區': [25.0833, 121.7500],
                '基隆市七堵區': [25.0833, 121.7000],
                // 新竹市
                '新竹市東區': [24.8000, 121.0167],
                '新竹市北區': [24.8167, 121.0000],
                '新竹市香山區': [24.7667, 120.9500],
                // 嘉義市
                '嘉義市東區': [23.4833, 120.4500],
                '嘉義市西區': [23.4833, 120.4167],
            };

            // 嘗試使用城市+行政區的組合鍵
            const cityDistrictKey = `${selectedCity}${district}`;
            const coordinates = defaultCoordinates[cityDistrictKey] || defaultCoordinates[district];
            if (coordinates) {
                mapRef.current.setView(coordinates, 12);
            }
        }
    }, [selectedCity]);

    // 性能監控
    const updatePerformanceMetrics = useCallback(() => {
        if (typeof performance !== 'undefined') {
            const now = performance.now();
            const memoryInfo = (performance as any).memory;

            setPerformanceMetrics({
                loadTime:
                    loadStartTime.current > 0 ? now - loadStartTime.current : 0,
                renderTime: now,
                memoryUsage: memoryInfo
                    ? Math.round(memoryInfo.usedJSHeapSize / 1024 / 1024)
                    : 0,
                markerCount:
                    (properties?.length || 0) + (clusters?.length || 0),
            });
        }
    }, [properties?.length, clusters?.length]);

    useEffect(() => {
        loadStartTime.current = performance.now();
        fetchCities();

        // 如果沒有選擇特定行政區，嘗試獲取用戶位置
        if (!selectedDistrict) {
            getUserLocation();
        }

        // 延遲初始載入以改善首次渲染性能
        const timer = setTimeout(() => {
            updateViewport(
                {
                    north: 25.2,
                    south: 24.9,
                    east: 121.8,
                    west: 121.3,
                    zoom: defaultZoom,
                },
                selectedDistrict && selectedDistrict.trim() !== '' ? selectedDistrict : undefined,
                selectedCity && selectedCity.trim() !== '' ? selectedCity : undefined,
            );
            setIsInitialLoad(false);
        }, 100);

        return () => clearTimeout(timer);
    }, [getUserLocation, selectedDistrict]);

    useEffect(() => {
        updatePerformanceMetrics();
    }, [properties, clusters, heatmapData, updatePerformanceMetrics]);

    useEffect(() => {
        if (mapRef.current) {
            const bounds = mapRef.current.getBounds();
            const zoom = mapRef.current.getZoom();
            updateViewport(
                {
                    north: bounds.getNorth(),
                    south: bounds.getSouth(),
                    east: bounds.getEast(),
                    west: bounds.getWest(),
                    zoom,
                },
                selectedDistrict,
                selectedCity,
            );
        }
    }, [selectedDistrict]);

    const fetchCities = async () => {
        try {
            const response = await fetch('/api/map/cities');
            const data = await response.json();
            if (data.success) {
                setCities(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch cities:', err);
        }
    };

    const fetchDistricts = async (city: string) => {
        if (!city) {
            setDistricts([]);
            return;
        }
        try {
            const response = await fetch(
                `/api/map/districts?city=${encodeURIComponent(city)}`,
            );
            const data = await response.json();
            if (data.success) {
                setDistricts(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch districts:', err);
        }
    };

    // 處理縣市選擇變更
    const handleCityChange = useCallback(async (city: string) => {
        setSelectedCity(city);
        setSelectedDistrict(''); // 預設選擇「全區」
        
        // 獲取行政區資料
        await fetchDistricts(city);
        
        // 選擇縣市時，地圖中心跳到該縣市的第一個行政區
        // 但行政區下拉選單仍顯示「全區」，讓用戶可以選擇特定行政區
        if (city) {
            try {
                // 直接從 API 獲取該縣市的行政區列表
                const response = await fetch(
                    `/api/map/districts?city=${encodeURIComponent(city)}`,
                );
                const data = await response.json();
                if (data.success && data.data && data.data.length > 0) {
                    const firstDistrict = data.data[0];
                    // 移動地圖中心到第一個行政區，傳遞城市參數
                    const params = new URLSearchParams();
                    params.append('district', firstDistrict.district);
                    params.append('city', city);
                    
                    const boundsResponse = await fetch(
                        `/api/map/district-bounds?${params.toString()}`,
                    );
                    const boundsData = await boundsResponse.json();
                    
                    if (boundsData.success && boundsData.bounds && mapRef.current) {
                        const { north, south, east, west } = boundsData.bounds;
                        const centerLat = (north + south) / 2;
                        const centerLng = (east + west) / 2;
                        mapRef.current.setView([centerLat, centerLng], 13);
                    }
                }
            } catch (err) {
                console.error('Failed to navigate to first district:', err);
            }
        }
    }, []);

    // 處理行政區選擇變更
    const handleDistrictChange = useCallback(
        async (district: string) => {
            setSelectedDistrict(district);
            if (district) {
                navigateToDistrict(district);
            } else {
                // 如果選擇"全區"
                if (selectedCity) {
                    // 如果有選擇縣市，移動到該縣市的第一個行政區
                    try {
                        const response = await fetch(
                            `/api/map/districts?city=${encodeURIComponent(selectedCity)}`,
                        );
                        const data = await response.json();
                        if (data.success && data.data && data.data.length > 0) {
                            const firstDistrict = data.data[0];
                            // 移動地圖中心到第一個行政區，但不改變行政區選擇
                            try {
                                await navigateToDistrict(firstDistrict.district);
                            } catch (navErr) {
                                console.error('Failed to navigate to district:', navErr);
                                // 如果導航失敗，嘗試直接使用城市中心點
                                const cityCenterResponse = await fetch(
                                    `/api/map/city-center?city=${encodeURIComponent(selectedCity)}`,
                                );
                                const cityCenterData = await cityCenterResponse.json();
                                if (cityCenterData.success && cityCenterData.center && mapRef.current) {
                                    const { lat, lng } = cityCenterData.center;
                                    mapRef.current.setView([lat, lng], 13);
                                }
                            }
                        }
                    } catch (err) {
                        console.error('Failed to navigate to first district:', err);
                    }
                } else {
                    // 如果沒有選擇縣市，回到用戶位置或預設位置
                    if (userLocation) {
                        if (mapRef.current) {
                            mapRef.current.setView(userLocation, 15);
                        }
                    }
                }
            }
        },
        [navigateToDistrict, userLocation, selectedCity],
    );

    // 優化的視口變更處理，加入節流
    const handleViewportChange = useCallback(
        throttle((viewport: any) => {
            updateViewport(viewport, selectedDistrict, selectedCity);
        }, 16), // 60fps
        [updateViewport, selectedDistrict, selectedCity],
    );

    // 優化的圖標創建，使用緩存
    const createCustomIcon = useCallback((rentPerPing: number, area: number) => {
        // 直接使用每坪租金進行分類
        const priceCategory =
            rentPerPing > 1000 ? 'high' : rentPerPing >= 600 ? 'medium' : 'low';

        if (iconCache.has(priceCategory)) {
            return iconCache.get(priceCategory)!;
        }

        const color =
            priceCategory === 'high'
                ? '#ef4444'
                : priceCategory === 'medium'
                  ? '#f97316'
                  : '#22c55e';

        const icon = L.divIcon({
            html: `<div class="marker-${priceCategory}" style="background-color: ${color}; width: 18px; height: 18px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            className: 'custom-marker',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
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
            <div className="flex h-full items-center justify-center">
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
            <div className="flex h-full items-center justify-center">
                <div className="text-center">
                    <p className="text-red-600 dark:text-red-400">{error}</p>
                    <button
                        onClick={() => {
                            if (mapRef.current) {
                                const bounds = mapRef.current.getBounds();
                                const zoom = mapRef.current.getZoom();
                                updateViewport(
                                    {
                                        north: bounds.getNorth(),
                                        south: bounds.getSouth(),
                                        east: bounds.getEast(),
                                        west: bounds.getWest(),
                                        zoom,
                                    },
                                    selectedDistrict,
                                    selectedCity,
                                );
                            }
                        }}
                        className="mt-2 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        重新載入
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            {/* 全台統計概覽 */}
            <div className="flex-shrink-0 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 dark:border-gray-700 dark:from-gray-800 dark:to-gray-700">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="text-center">
                        <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {properties?.length || 0}
                        </div>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            熱門區域
                        </div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                            {statistics?.total_properties?.toLocaleString() ||
                                0}
                        </div>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            總租屋數
                        </div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {statistics?.cities
                                ? Object.keys(statistics.cities).length
                                : 0}
                        </div>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            涵蓋縣市
                        </div>
                    </div>
                    <div className="text-center">
                        <div className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            {statistics?.avg_rent_per_ping
                                ? Math.round(
                                      statistics.avg_rent_per_ping,
                                  ).toLocaleString()
                                : 0}
                        </div>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            平均每坪租金
                        </div>
                    </div>
                </div>
            </div>

            {/* 控制面板 */}
            <div className="flex flex-shrink-0 flex-wrap items-center gap-4 border-b border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div className="flex items-center gap-2">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        縣市：
                    </label>
                    <select
                        value={selectedCity}
                        onChange={(e) => handleCityChange(e.target.value)}
                        className="rounded-md border border-gray-300 bg-white px-3 py-1 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        title="選擇縣市"
                    >
                        <option value="">全台縣市</option>
                        {cities?.map((city) => (
                            <option key={city.city} value={city.city}>
                                {city.city} ({city.property_count})
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        行政區：
                    </label>
                    <select
                        value={selectedDistrict}
                        onChange={(e) => handleDistrictChange(e.target.value)}
                        className="rounded-md border border-gray-300 bg-white px-3 py-1 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        title="選擇行政區"
                        disabled={!selectedCity}
                    >
                        {!selectedCity && <option value="">全部行政區</option>}
                        {selectedCity && <option value="">全區</option>}
                        {districts?.map((district) => (
                            <option
                                key={district.district}
                                value={district.district}
                            >
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
                        onChange={(e) =>
                            toggleDisplayMode(e.target.value as any)
                        }
                        className="rounded-md border border-gray-300 bg-white px-3 py-1 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        title="選擇地圖顯示模式"
                    >
                        <option value="properties">個別標記</option>
                        <option value="clusters">區域統計</option>
                        <option value="heatmap">價格分析</option>
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={getUserLocation}
                        disabled={loading}
                        className="group relative rounded bg-purple-600 px-3 py-1 text-sm text-white hover:bg-purple-700 disabled:opacity-50"
                        title="獲取我的位置並移動地圖到該位置"
                    >
                        📍 我的位置
                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 transform rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            獲取我的位置並移動地圖到該位置
                            <div className="absolute top-full left-1/2 -translate-x-1/2 transform border-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </button>
                    <button
                        onClick={() => toggleDisplayMode('clusters')}
                        disabled={loading}
                        className={`group relative rounded px-3 py-1 text-sm text-white hover:opacity-80 disabled:opacity-50 ${
                            displayMode === 'clusters' 
                                ? 'bg-blue-700 ring-2 ring-blue-300' 
                                : 'bg-blue-600 hover:bg-blue-700'
                        }`}
                        title="顯示各行政區的租屋統計資訊，包含平均租金和租屋數量。"
                    >
                        區域統計
                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 transform rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            顯示各行政區的租屋統計資訊，包含平均租金和租屋數量
                            <div className="absolute top-full left-1/2 -translate-x-1/2 transform border-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </button>
                    <button
                        onClick={() => toggleDisplayMode('heatmap')}
                        disabled={loading}
                        className={`group relative rounded px-3 py-1 text-sm text-white hover:opacity-80 disabled:opacity-50 ${
                            displayMode === 'heatmap' 
                                ? 'bg-green-700 ring-2 ring-green-300' 
                                : 'bg-green-600 hover:bg-green-700'
                        }`}
                        title="顯示不同價格區間的租屋分布，顏色代表價格等級。"
                    >
                        價格分析
                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 transform rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            顯示不同價格區間的租屋分布，顏色代表價格等級
                            <div className="absolute top-full left-1/2 -translate-x-1/2 transform border-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </button>
                </div>

                <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <span className="text-xs text-gray-500 dark:text-gray-500">
                        每坪租金等級：
                    </span>
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-green-500"></div>
                        <span>低租金 (&lt; 600元/坪)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-orange-500"></div>
                        <span>中租金 (600-1000元/坪)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-3 w-3 rounded-full bg-red-500"></div>
                        <span>高租金 (&gt; 1000元/坪)</span>
                    </div>
                </div>

                <div className="ml-auto text-sm text-gray-600 dark:text-gray-400">
                    {displayMode === 'properties' &&
                        `顯示 ${properties?.length || 0} 個租屋標記`}
                    {displayMode === 'clusters' &&
                        `顯示 ${clusters?.length || 0} 個行政區統計`}
                    {displayMode === 'heatmap' && `顯示 ${heatmapData?.length || 0} 個價格分析點`}
                    {loading && ' (載入中...)'}
                    {locationError && (
                        <span className="ml-2 text-xs text-red-500">
                            {locationError}
                        </span>
                    )}
                </div>
            </div>

            {/* 地圖容器 */}
            <div className="relative flex-1" style={{ minHeight: '400px' }}>
                <MapContainer
                    center={defaultCenter}
                    zoom={defaultZoom}
                    style={{
                        height: '100%',
                        width: '100%',
                        minHeight: '400px',
                    }}
                    ref={mapRef}
                    className="h-full w-full"
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />

                    <MapEventHandler onViewportChange={handleViewportChange} />
                    <FullscreenControl />

                    {/* 聚合區域標記 */}
                    {displayMode === 'properties' &&
                        properties?.map((property) => (
                            <Marker
                                key={property.id}
                                position={[
                                    property.location.lat,
                                    property.location.lng,
                                ]}
                                icon={createCustomIcon(
                                    property.price,
                                    property.area,
                                )}
                            >
                                <Popup className="rental-popup">
                                    <div className="min-w-80 p-4">
                                        <h3 className="mb-3 text-lg font-semibold text-gray-900">
                                            {property.title}
                                        </h3>

                                        <div className="space-y-2 text-sm">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div className="rounded bg-blue-50 p-2">
                                                    <div className="text-xs text-blue-600">
                                                        物件數量
                                                    </div>
                                                    <div className="font-semibold text-blue-800">
                                                        {
                                                            property.property_count
                                                        }{' '}
                                                        筆
                                                    </div>
                                                </div>
                                                <div className="rounded bg-green-50 p-2">
                                                    <div className="text-xs text-green-600">
                                                        平均每坪租金
                                                    </div>
                                                    <div className="font-semibold text-green-800">
                                                        {formatCurrency(
                                                            property.price,
                                                        )}
                                                        /坪
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="space-y-1 text-gray-600">
                                                <div className="flex justify-between">
                                                    <span>平均租金：</span>
                                                    <span className="font-medium">
                                                        {formatCurrency(
                                                            property.avg_rent,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>租金範圍：</span>
                                                    <span className="font-medium">
                                                        {formatCurrency(
                                                            property.min_rent,
                                                        )}{' '}
                                                        -{' '}
                                                        {formatCurrency(
                                                            property.max_rent,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>平均面積：</span>
                                                    <span className="font-medium">
                                                        {typeof property.area === 'number' 
                                                            ? property.area.toFixed(1)
                                                            : property.area || 'N/A'
                                                        }{' '}
                                                        坪
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="border-t pt-2">
                                                <div className="mb-1 text-xs text-gray-500">
                                                    設施比例
                                                </div>
                                                <div className="grid grid-cols-3 gap-1 text-xs">
                                                    <div className="text-center">
                                                        <div className="text-orange-600">
                                                            電梯
                                                        </div>
                                                        <div className="font-medium">
                                                            {
                                                                property.elevator_ratio
                                                            }
                                                            %
                                                        </div>
                                                    </div>
                                                    <div className="text-center">
                                                        <div className="text-purple-600">
                                                            管理
                                                        </div>
                                                        <div className="font-medium">
                                                            {
                                                                property.management_ratio
                                                            }
                                                            %
                                                        </div>
                                                    </div>
                                                    <div className="text-center">
                                                        <div className="text-green-600">
                                                            傢俱
                                                        </div>
                                                        <div className="font-medium">
                                                            {
                                                                property.furniture_ratio
                                                            }
                                                            %
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Popup>
                            </Marker>
                        ))}

                    {/* 行政區統計標記 */}
                    {displayMode === 'clusters' &&
                        clusters?.map((district) => {
                            // 根據租屋數量確定大小
                            const count = district.count || 0;
                            const size = Math.min(Math.max(count / 5, 8), 25);

                            // 根據平均租金選擇顏色
                            const avgRent = district.avg_rent_per_ping || 0;
                            let color = '#22c55e'; // 預設綠色
                            let borderColor = '#16a34a';

                            if (avgRent > 0) {
                                if (avgRent >= 1000) {
                                    color = '#dc2626'; // 高價紅色
                                    borderColor = '#991b1b';
                                } else if (avgRent >= 600) {
                                    color = '#f97316'; // 中價橙色
                                    borderColor = '#ea580c';
                                } else if (avgRent >= 300) {
                                    color = '#eab308'; // 低價黃色
                                    borderColor = '#ca8a04';
                                } else {
                                    color = '#22c55e'; // 超低價綠色
                                    borderColor = '#16a34a';
                                }
                            }

                            return (
                                <CircleMarker
                                    key={district.id}
                                    center={[
                                        district.center.lat,
                                        district.center.lng,
                                    ]}
                                    radius={size}
                                    pathOptions={{
                                        fillColor: color,
                                        color: borderColor,
                                        weight: 2,
                                        opacity: 0.9,
                                        fillOpacity: 0.7,
                                    }}
                                >
                                    <Popup className="enhanced-cluster-popup">
                                        <div className="min-w-72 p-3">
                                            <div className="mb-3 flex items-center justify-between">
                                                <h3 className="font-semibold text-gray-900">
                                                    {district.city} {district.district}
                                                </h3>
                                                <span
                                                    className={`rounded px-2 py-1 text-xs font-medium text-white`}
                                                    style={{
                                                        backgroundColor: color,
                                                    }}
                                                >
                                                    {count} 筆
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div className="space-y-1">
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            租屋數量：
                                                        </span>
                                                        <span className="font-medium">
                                                            {count} 筆
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            平均面積：
                                                        </span>
                                                        <span className="font-medium">
                                                            {district.avg_area_ping?.toFixed(1)} 坪
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="space-y-1">
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            平均租金：
                                                        </span>
                                                        <span className="font-medium text-blue-600">
                                                            {avgRent.toLocaleString()} 元/坪
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            租金範圍：
                                                        </span>
                                                        <span className="text-xs font-medium text-green-600">
                                                            {district.min_rent_per_ping?.toLocaleString()} - {district.max_rent_per_ping?.toLocaleString()} 元/坪
                                                        </span>
                                                    </div>
                                                    </div>
                                            </div>

                                        </div>
                                    </Popup>
                                </CircleMarker>
                            );
                        })}

                    {/* 價格分析點 */}
                    {displayMode === 'heatmap' &&
                        heatmapData?.map((point, index) => {
                            // 根據價格等級確定大小和顏色
                            const radius = Math.max(5, Math.min(15, 8 + point.weight * 5));
                            const color = point.color || '#f59e0b';
                            const borderColor = point.level === 'premium' ? '#6b21a8' : 
                                               point.level === 'high' ? '#dc2626' :
                                               point.level === 'medium' ? '#f59e0b' : '#22c55e';

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
                                        fillOpacity:
                                            point.intensity || point.weight,
                                    }}
                                >
                                    <Popup>
                                        <div className="min-w-48 p-2">
                                            <h4 className="mb-2 font-semibold text-gray-900">
                                                價格分析點
                                            </h4>
                                            <div className="space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">
                                                        價格等級：
                                                    </span>
                                                    <span className="font-medium" style={{ color: color }}>
                                                        {point.level === 'premium' ? '高級' :
                                                         point.level === 'high' ? '高價' :
                                                         point.level === 'medium' ? '中價' : '低價'}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">
                                                        每坪租金：
                                                    </span>
                                                    <span className="font-medium text-blue-600">
                                                        {point.rent_per_ping?.toLocaleString()} 元/坪
                                                    </span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">
                                                        總租金：
                                                    </span>
                                                    <span className="font-medium text-green-600">
                                                        {point.total_rent?.toLocaleString()} 元
                                                    </span>
                                                </div>
                                                {point.intensity && (
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            強度：
                                                        </span>
                                                        <span className="font-medium">
                                                            {(
                                                                (point.intensity || 0) *
                                                                100
                                                            ).toFixed(1)}
                                                            %
                                                        </span>
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

