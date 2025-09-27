import { useState, useEffect, useCallback } from 'react';
import webSocketService from '../services/WebSocketService';

export interface MapUpdateData {
    type: 'properties' | 'clusters' | 'heatmap' | 'statistics';
    data: any;
    bounds?: {
        north: number;
        south: number;
        east: number;
        west: number;
    };
    zoom?: number;
    timestamp: number;
}

export interface UseRealtimeMapReturn {
    mapData: MapUpdateData | null;
    isReceivingUpdates: boolean;
    lastUpdateTime?: Date;
    subscribeToMapUpdates: () => void;
    unsubscribeFromMapUpdates: () => void;
    clearMapData: () => void;
}

export const useRealtimeMap = (): UseRealtimeMapReturn => {
    const [mapData, setMapData] = useState<MapUpdateData | null>(null);
    const [isReceivingUpdates, setIsReceivingUpdates] = useState(false);
    const [lastUpdateTime, setLastUpdateTime] = useState<Date>();

    // 監聽地圖更新
    useEffect(() => {
        const handleMapUpdate = (data: any) => {
            const updateData: MapUpdateData = {
                type: data.type || 'properties',
                data: data.data,
                bounds: data.bounds,
                zoom: data.zoom,
                timestamp: data.timestamp || Date.now(),
            };

            setMapData(updateData);
            setLastUpdateTime(new Date());
            setIsReceivingUpdates(true);

            // 3秒後停止接收指示器
            setTimeout(() => {
                setIsReceivingUpdates(false);
            }, 3000);
        };

        webSocketService.on('mapUpdate', handleMapUpdate);

        return () => {
            webSocketService.off('mapUpdate', handleMapUpdate);
        };
    }, []);

    const subscribeToMapUpdates = useCallback(() => {
        webSocketService.subscribeToMapUpdates();
    }, []);

    const unsubscribeFromMapUpdates = useCallback(() => {
        webSocketService.unsubscribeFromMapUpdates();
    }, []);

    const clearMapData = useCallback(() => {
        setMapData(null);
        setIsReceivingUpdates(false);
    }, []);

    return {
        mapData,
        isReceivingUpdates,
        lastUpdateTime,
        subscribeToMapUpdates,
        unsubscribeFromMapUpdates,
        clearMapData,
    };
};
