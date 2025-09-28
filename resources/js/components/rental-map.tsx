import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
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
            // 動態獲取該行政區的實際座標範圍
            const response = await fetch(
                `/api/map/district-bounds?district=${encodeURIComponent(district)}`,
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
                // 如果無法獲取邊界，使用預設的台北市座標
                const defaultCoordinates: { [key: string]: [number, number] } =
                    {
                        中正區: [25.0324, 121.5194],
                        大同區: [25.0631, 121.512],
                        中山區: [25.064, 121.525],
                        松山區: [25.05, 121.577],
                        大安區: [25.0264, 121.5435],
                        萬華區: [25.036, 121.499],
                        信義區: [25.033, 121.5654],
                        士林區: [25.088, 121.525],
                        北投區: [25.132, 121.499],
                        內湖區: [25.069, 121.594],
                        南港區: [25.054, 121.606],
                        文山區: [25.004, 121.57],
                    };

                const coordinates = defaultCoordinates[district];
                if (coordinates) {
                    mapRef.current.setView(coordinates, 12);
                }
            }
        } catch (error) {
            console.error('Failed to get district bounds:', error);
            // 降級處理：使用預設座標
            const defaultCoordinates: { [key: string]: [number, number] } = {
                中正區: [25.0324, 121.5194],
                中山區: [25.064, 121.525],
                信義區: [25.033, 121.5654],
                內湖區: [25.069, 121.594],
                北投區: [25.132, 121.499],
                士林區: [25.088, 121.525],
                大安區: [25.0264, 121.5435],
                文山區: [25.004, 121.57],
                松山區: [25.05, 121.577],
                萬華區: [25.036, 121.499],
            };

            const coordinates = defaultCoordinates[district];
            if (coordinates) {
                mapRef.current.setView(coordinates, 12);
            }
        }
    }, []);

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
        await fetchDistricts(city);
        
        // 選擇縣市時顯示該縣市的全部資料，預設為「全區」
        // 用戶可以進一步選擇特定行政區
    }, []);

    // 處理行政區選擇變更
    const handleDistrictChange = useCallback(
        (district: string) => {
            setSelectedDistrict(district);
            if (district) {
                navigateToDistrict(district);
            } else {
                // 如果選擇"全部區域"，回到用戶位置或預設位置
                if (userLocation) {
                    if (mapRef.current) {
                        mapRef.current.setView(userLocation, 15);
                    }
                }
            }
        },
        [navigateToDistrict, userLocation],
    );

    // 優化的視口變更處理，加入節流
    const handleViewportChange = useCallback(
        throttle((viewport: any) => {
            updateViewport(viewport, selectedDistrict);
        }, 16), // 60fps
        [updateViewport, selectedDistrict],
    );

    // 優化的圖標創建，使用緩存
    const createCustomIcon = useCallback((totalRent: number, area: number) => {
        // 計算每坪租金（將平方公尺轉換為坪數）
        const rentPerPing = totalRent / (area / 3.30579);
        const priceCategory =
            rentPerPing > 1000 ? 'high' : rentPerPing > 600 ? 'medium' : 'low';

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
                        <option value="properties">區域統計</option>
                        <option value="clusters">智慧群組</option>
                        <option value="heatmap">租金熱力圖</option>
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
                        onClick={() => getAIClusters('kmeans', 15)}
                        disabled={loading}
                        className="group relative rounded bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
                        title="將附近的租屋標記合併成群組，讓地圖更清晰易讀。適合查看區域密度。"
                    >
                        智慧群組
                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 transform rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            將附近的租屋標記合併成群組，讓地圖更清晰易讀
                            <div className="absolute top-full left-1/2 -translate-x-1/2 transform border-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </button>
                    <button
                        onClick={() => getAIHeatmap('medium')}
                        disabled={loading}
                        className="group relative rounded bg-green-600 px-3 py-1 text-sm text-white hover:bg-green-700 disabled:opacity-50"
                        title="顯示租金密度分布，顏色越深表示租金越高。適合分析價格趨勢。"
                    >
                        租金熱力圖
                        <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 transform rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            顯示租金密度分布，顏色越深表示租金越高
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
                        `顯示 ${clusters?.length || 0} 個智慧群組`}
                    {displayMode === 'heatmap' && `租金密度分布圖`}
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

                    {/* AI 聚合標記 - 增強視覺效果 */}
                    {displayMode === 'clusters' &&
                        clusters?.map((cluster) => {
                            // 使用視覺等級來確定大小和顏色
                            const visualLevel =
                                cluster.visual_level ||
                                Math.min(
                                    5,
                                    Math.max(
                                        1,
                                        Math.floor(cluster.count / 10) + 1,
                                    ),
                                );
                            const baseSize = 15;
                            const size = Math.min(
                                Math.max(baseSize + visualLevel * 6, 15),
                                70,
                            );

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
                                    center={[
                                        cluster.center.lat,
                                        cluster.center.lng,
                                    ]}
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
                                        <div className="min-w-72 p-3">
                                            <div className="mb-3 flex items-center justify-between">
                                                <h3 className="font-semibold text-gray-900">
                                                    AI 聚合區域
                                                </h3>
                                                <span
                                                    className={`rounded px-2 py-1 text-xs font-medium text-white`}
                                                    style={{
                                                        backgroundColor: color,
                                                    }}
                                                >
                                                    等級 {visualLevel}
                                                </span>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div className="space-y-1">
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            物件數量：
                                                        </span>
                                                        <span className="font-medium">
                                                            {cluster.count}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            覆蓋半徑：
                                                        </span>
                                                        <span className="font-medium">
                                                            {cluster.radius_km?.toFixed(
                                                                2,
                                                            )}{' '}
                                                            km
                                                        </span>
                                                    </div>
                                                    {cluster.density && (
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">
                                                                密度：
                                                            </span>
                                                            <span className="font-medium">
                                                                {cluster.density.toFixed(
                                                                    1,
                                                                )}
                                                                /km²
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>

                                                {priceStats && (
                                                    <div className="space-y-1">
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">
                                                                平均租金：
                                                            </span>
                                                            <span className="font-medium text-blue-600">
                                                                {formatCurrency(
                                                                    priceStats.avg,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">
                                                                租金範圍：
                                                            </span>
                                                            <span className="text-xs font-medium text-green-600">
                                                                {formatCurrency(
                                                                    priceStats.min,
                                                                )}{' '}
                                                                -{' '}
                                                                {formatCurrency(
                                                                    priceStats.max,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">
                                                                中位數：
                                                            </span>
                                                            <span className="font-medium">
                                                                {formatCurrency(
                                                                    priceStats.median,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>

                                            {/* 價格分佈視覺化 */}
                                            {priceStats && (
                                                <div className="mt-3 border-t border-gray-200 pt-3">
                                                    <div className="mb-1 text-xs text-gray-600">
                                                        價格分佈
                                                    </div>
                                                    <div className="flex h-2 overflow-hidden rounded bg-gray-200">
                                                        <div
                                                            className="bg-green-500"
                                                            style={{
                                                                width: '33.33%',
                                                            }}
                                                            title={`低價: ${formatCurrency(priceStats.min)}`}
                                                        />
                                                        <div
                                                            className="bg-yellow-500"
                                                            style={{
                                                                width: '33.33%',
                                                            }}
                                                            title={`中價: ${formatCurrency(priceStats.median)}`}
                                                        />
                                                        <div
                                                            className="bg-red-500"
                                                            style={{
                                                                width: '33.34%',
                                                            }}
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
                    {displayMode === 'heatmap' &&
                        heatmapData?.map((point, index) => {
                            // 使用進階顏色和大小計算
                            const radius =
                                point.radius ||
                                Math.max(3, Math.min(15, 5 + point.weight * 8));
                            const color =
                                point.color ||
                                (point.weight > 0.7
                                    ? 'rgba(255, 0, 0, 0.8)'
                                    : point.weight > 0.4
                                      ? 'rgba(255, 255, 0, 0.7)'
                                      : 'rgba(0, 255, 0, 0.6)');
                            const borderColor =
                                point.weight > 0.7
                                    ? '#dc2626'
                                    : point.weight > 0.4
                                      ? '#ca8a04'
                                      : '#16a34a';

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
                                                熱力圖區域
                                            </h4>
                                            <div className="space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">
                                                        權重：
                                                    </span>
                                                    <span className="font-medium">
                                                        {(
                                                            (point.weight || 0) * 100
                                                        ).toFixed(1)}
                                                        %
                                                    </span>
                                                </div>
                                                {point.count && (
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            資料點：
                                                        </span>
                                                        <span className="font-medium">
                                                            {point.count}
                                                        </span>
                                                    </div>
                                                )}
                                                {point.avg_price && (
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            平均租金：
                                                        </span>
                                                        <span className="font-medium text-blue-600">
                                                            {formatCurrency(
                                                                point.avg_price,
                                                            )}
                                                        </span>
                                                    </div>
                                                )}
                                                {point.price_range && (
                                                    <div className="flex justify-between">
                                                        <span className="text-gray-600">
                                                            價格區間：
                                                        </span>
                                                        <span className="font-medium text-green-600">
                                                            {point.price_range}
                                                        </span>
                                                    </div>
                                                )}
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
