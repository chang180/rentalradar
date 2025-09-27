import { useCallback, useEffect, useState } from 'react';
import mapWebSocketService, {
    MapUpdateRequest,
    MapUpdateResponse,
} from '../services/MapWebSocketService';

export interface UseRealtimeMapDataOptions {
    autoSubscribe?: boolean;
    debounceMs?: number;
    maxRetries?: number;
}

export interface UseRealtimeMapDataReturn {
    data: MapUpdateResponse | null;
    isLoading: boolean;
    error: string | null;
    lastUpdate: Date | null;
    requestUpdate: (request: MapUpdateRequest) => Promise<void>;
    clearData: () => void;
    retry: () => void;
}

export const useRealtimeMapData = (
    options: UseRealtimeMapDataOptions = {},
): UseRealtimeMapDataReturn => {
    const { autoSubscribe = true, debounceMs = 300, maxRetries = 3 } = options;

    const [data, setData] = useState<MapUpdateResponse | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
    const [retryCount, setRetryCount] = useState(0);
    const [debounceTimer, setDebounceTimer] = useState<NodeJS.Timeout | null>(
        null,
    );

    // 處理地圖更新
    const handleMapUpdate = useCallback((updateData: MapUpdateResponse) => {
        setData(updateData);
        setLastUpdate(new Date());
        setIsLoading(false);
        setError(null);
        setRetryCount(0);
    }, []);

    // 處理地圖錯誤
    const handleMapError = useCallback((errorData: any) => {
        setError(errorData.message || '地圖更新失敗');
        setIsLoading(false);
    }, []);

    // 處理效能更新
    const handlePerformanceUpdate = useCallback((metrics: any) => {
        // 可以根據效能指標調整請求頻率
        console.log('效能指標更新:', metrics);
    }, []);

    // 設置監聽器
    useEffect(() => {
        if (autoSubscribe) {
            mapWebSocketService.subscribeToMapUpdates(handleMapUpdate);
            mapWebSocketService.onMapError(handleMapError);
            mapWebSocketService.onPerformanceUpdate(handlePerformanceUpdate);
        }

        return () => {
            mapWebSocketService.unsubscribeFromMapUpdates(handleMapUpdate);
        };
    }, [
        autoSubscribe,
        handleMapUpdate,
        handleMapError,
        handlePerformanceUpdate,
    ]);

    // 請求更新
    const requestUpdate = useCallback(
        async (request: MapUpdateRequest) => {
            // 清除之前的防抖計時器
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            // 設置防抖
            const timer = setTimeout(async () => {
                try {
                    setIsLoading(true);
                    setError(null);

                    await mapWebSocketService.requestMapUpdate(request);
                } catch (err) {
                    setError(err instanceof Error ? err.message : '請求失敗');
                    setIsLoading(false);
                }
            }, debounceMs);

            setDebounceTimer(timer);
        },
        [debounceMs, debounceTimer],
    );

    // 重試
    const retry = useCallback(() => {
        if (retryCount < maxRetries && data) {
            setRetryCount((prev) => prev + 1);
            // 重新請求最後一次更新
            const lastRequest = {
                bounds: data.bounds,
                zoom: data.zoom,
                type: data.type as any,
            };
            requestUpdate(lastRequest);
        }
    }, [retryCount, maxRetries, data, requestUpdate]);

    // 清除資料
    const clearData = useCallback(() => {
        setData(null);
        setError(null);
        setIsLoading(false);
        setLastUpdate(null);
        setRetryCount(0);
    }, []);

    // 清理防抖計時器
    useEffect(() => {
        return () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
        };
    }, [debounceTimer]);

    return {
        data,
        isLoading,
        error,
        lastUpdate,
        requestUpdate,
        clearData,
        retry,
    };
};
