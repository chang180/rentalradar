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
import { AlertCircle } from 'lucide-react';

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

interface RentalMapProps {
    onStatsUpdate?: (statistics: any, properties: any[]) => void;
}

const RentalMap = memo(({ onStatsUpdate }: RentalMapProps = {}) => {
    const [selectedCity, setSelectedCity] = useState<string>('');
    const [selectedDistrict, setSelectedDistrict] = useState<string>('');
    const [cities, setCities] = useState<any[]>([]);
    const [districts, setDistricts] = useState<
        { district: string; property_count: number }[]
    >([]);
    // 移除複雜的顯示模式，保留自動位置功能
    const [userLocation, setUserLocation] = useState<[number, number] | null>(null);
    const [locationError, setLocationError] = useState<string | null>(null);
    const [performanceMetrics, setPerformanceMetrics] =
        useState<PerformanceMetrics | null>(null);
    const [isInitialLoad, setIsInitialLoad] = useState(true);

    const mapRef = useRef<L.Map | null>(null);
    const loadStartTime = useRef<number>(0);

    // 使用 AI 地圖 Hook - 簡化為只顯示租屋標記
    const {
        properties,
        loading,
        error,
        statistics,
        updateViewport,
        // 明確排除不需要的變數以避免錯誤
        clusters: _clusters,
        heatmapData: _heatmapData,
        displayMode: _displayMode,
    } = useAIMap({
        enableClustering: false,
        enableHeatmap: false,
        clusterThreshold: 50,
        autoOptimize: true,
    });

    // 台北市中心座標（預設位置）
    const defaultCenter: [number, number] = [25.033, 121.5654];
    const defaultZoom = 11;

    // 自動獲取用戶位置（無手動按鈕）
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
            } else {
                // 如果降級處理中沒有找到座標，嘗試使用城市中心點
                try {
                    const cityCenterResponse = await fetch(
                        `/api/map/city-center?city=${encodeURIComponent(selectedCity)}`,
                    );
                    const cityCenterData = await cityCenterResponse.json();
                    if (cityCenterData.success && cityCenterData.center && mapRef.current) {
                        const { lat, lng } = cityCenterData.center;
                        mapRef.current.setView([lat, lng], 13);
                    }
                } catch (cityCenterErr) {
                    console.error('Failed to get city center:', cityCenterErr);
                }
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
                markerCount: properties?.length || 0,
            });
        }
    }, [properties?.length]);

    useEffect(() => {
        loadStartTime.current = performance.now();
        fetchCities();

        // 如果沒有選擇特定行政區，自動獲取用戶位置
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
        // 通知父組件統計數據更新
        if (onStatsUpdate) {
            onStatsUpdate(statistics, properties || []);
        }
    }, [properties, statistics, updatePerformanceMetrics, onStatsUpdate]);

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
        
        if (city) {
            // 選擇特定縣市時，地圖中心跳到該縣市的第一個行政區
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
        } else {
            // 選擇「全台縣市」時，回到用戶位置或預設位置，並重新標記全部
            if (userLocation && mapRef.current) {
                // 如果有用戶位置，移動到用戶位置
                mapRef.current.setView(userLocation, 15);
            } else if (mapRef.current) {
                // 如果沒有用戶位置，回到預設位置（台北市中心）
                mapRef.current.setView(defaultCenter, defaultZoom);
            }
            
            // 重新載入全台資料，觸發重新標記
            if (mapRef.current) {
                const bounds = mapRef.current.getBounds();
                const viewport = {
                    north: bounds.getNorth(),
                    south: bounds.getSouth(),
                    east: bounds.getEast(),
                    west: bounds.getWest(),
                    zoom: mapRef.current.getZoom(),
                };
                updateViewport(viewport, '', '');
            }
        }
    }, [userLocation, defaultCenter, defaultZoom, updateViewport]);

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
                    } else if (mapRef.current) {
                        mapRef.current.setView(defaultCenter, defaultZoom);
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

    // 優化的圖標創建，使用緩存 - 新增更多租金等級
    const createCustomIcon = useCallback((rentPerPing: number, area: number) => {
        // 更細緻的租金等級分類
        let priceCategory: string;
        let color: string;
        
        if (rentPerPing >= 1500) {
            priceCategory = 'very-high';
            color = '#dc2626'; // 深紅色
        } else if (rentPerPing >= 1200) {
            priceCategory = 'high';
            color = '#ef4444'; // 紅色
        } else if (rentPerPing >= 900) {
            priceCategory = 'medium-high';
            color = '#f97316'; // 橙色
        } else if (rentPerPing >= 600) {
            priceCategory = 'medium';
            color = '#eab308'; // 黃色
        } else if (rentPerPing >= 400) {
            priceCategory = 'low-medium';
            color = '#84cc16'; // 淺綠色
        } else {
            priceCategory = 'low';
            color = '#22c55e'; // 綠色
        }

        if (iconCache.has(priceCategory)) {
            return iconCache.get(priceCategory)!;
        }

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
            {/* 統計概覽已移到標題區域 */}

            {/* 緊湊控制面板 */}
            <div className="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                <div className="flex items-center gap-1">
                    <label className="text-xs font-medium text-gray-700 dark:text-gray-300">
                        縣市：
                    </label>
                    <select
                        value={selectedCity}
                        onChange={(e) => handleCityChange(e.target.value)}
                        className="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
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

                <div className="flex items-center gap-1">
                    <label className="text-xs font-medium text-gray-700 dark:text-gray-300">
                        行政區：
                    </label>
                    <select
                        value={selectedDistrict}
                        onChange={(e) => handleDistrictChange(e.target.value)}
                        className="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
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

                {/* 緊湊租金等級圖例（含價格範圍） */}
                <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                    <span className="text-gray-500 dark:text-gray-500">租金等級：</span>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-green-500"></div>
                        <span>超低 (&lt;400)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-lime-500"></div>
                        <span>低 (400-600)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-yellow-500"></div>
                        <span>中低 (600-900)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-orange-500"></div>
                        <span>中高 (900-1200)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-red-500"></div>
                        <span>高 (1200-1500)</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div className="h-2 w-2 rounded-full bg-red-700"></div>
                        <span>超高 (&gt;1500)</span>
                    </div>
                </div>

                <div className="ml-auto text-xs text-gray-600 dark:text-gray-400">
                    {`${properties?.length || 0} 個標記`}
                    {loading && ' (載入中...)'}
                </div>
            </div>

            {/* 地圖容器 */}
            <div className="relative flex-1" style={{ minHeight: '400px' }}>
                {/* 空資料狀態提示 */}
                {!loading && !error && !isInitialLoad &&
                 (!properties || properties.length === 0) && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-gray-50/90 dark:bg-gray-900/90">
                        <div className="text-center">
                            <div className="mb-4">
                                <div className="mx-auto h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <AlertCircle className="h-8 w-8 text-gray-400 dark:text-gray-500" />
                                </div>
                            </div>
                            <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                暫無租屋資料
                            </h3>
                            <p className="text-gray-600 dark:text-gray-400">
                                {selectedCity || selectedDistrict 
                                    ? '所選區域目前沒有可用的租屋資料，請選擇其他區域或查看全台資料'
                                    : '目前沒有可用的租屋資料，請稍後再試或聯繫管理員'
                                }
                            </p>
                            {(selectedCity || selectedDistrict) && (
                                <button
                                    onClick={() => {
                                        setSelectedCity('');
                                        setSelectedDistrict('');
                                        if (mapRef.current) {
                                            mapRef.current.setView(defaultCenter, defaultZoom);
                                        }
                                    }}
                                    className="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                >
                                    查看全台資料
                                </button>
                            )}
                        </div>
                    </div>
                )}
                
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

                    {/* 租屋標記 */}
                    {properties?.map((property) => (
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

                    {/* 只顯示租屋標記，移除複雜的統計和分析功能 */}
                </MapContainer>
            </div>
        </div>
    );
});

export default RentalMap;

