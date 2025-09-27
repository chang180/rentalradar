import webSocketService from './WebSocketService';

export interface MapBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

export interface MapUpdateRequest {
    bounds: MapBounds;
    zoom: number;
    type: 'properties' | 'clusters' | 'heatmap' | 'statistics';
    filters?: {
        priceRange?: [number, number];
        areaRange?: [number, number];
        districts?: string[];
        buildingTypes?: string[];
    };
}

export interface MapUpdateResponse {
    type: string;
    data: any;
    bounds: MapBounds;
    zoom: number;
    timestamp: number;
    performance?: {
        response_time: number;
        memory_usage: number;
        query_count: number;
        cache_hit_rate: number;
    };
}

export class MapWebSocketService {
    private webSocketService = webSocketService;

    /**
     * 請求地圖資料更新
     */
    async requestMapUpdate(request: MapUpdateRequest): Promise<void> {
        // 發送地圖更新請求到後端
        this.webSocketService.emit('mapUpdateRequest', {
            bounds: request.bounds,
            zoom: request.zoom,
            type: request.type,
            filters: request.filters,
            timestamp: Date.now(),
        });
    }

    /**
     * 訂閱地圖更新
     */
    subscribeToMapUpdates(callback: (data: MapUpdateResponse) => void): void {
        this.webSocketService.on('mapUpdate', callback);
    }

    /**
     * 取消訂閱地圖更新
     */
    unsubscribeFromMapUpdates(
        callback: (data: MapUpdateResponse) => void,
    ): void {
        this.webSocketService.off('mapUpdate', callback);
    }

    /**
     * 請求特定區域的聚合資料
     */
    async requestClusterData(bounds: MapBounds, zoom: number): Promise<void> {
        await this.requestMapUpdate({
            bounds,
            zoom,
            type: 'clusters',
        });
    }

    /**
     * 請求熱力圖資料
     */
    async requestHeatmapData(bounds: MapBounds, zoom: number): Promise<void> {
        await this.requestMapUpdate({
            bounds,
            zoom,
            type: 'heatmap',
        });
    }

    /**
     * 請求統計資料
     */
    async requestStatisticsData(bounds: MapBounds): Promise<void> {
        await this.requestMapUpdate({
            bounds,
            zoom: 0, // 統計資料不需要縮放級別
            type: 'statistics',
        });
    }

    /**
     * 請求物件資料
     */
    async requestPropertiesData(
        bounds: MapBounds,
        zoom: number,
        filters?: MapUpdateRequest['filters'],
    ): Promise<void> {
        await this.requestMapUpdate({
            bounds,
            zoom,
            type: 'properties',
            filters,
        });
    }

    /**
     * 監聽地圖效能指標
     */
    onPerformanceUpdate(callback: (metrics: any) => void): void {
        this.webSocketService.on('systemStatus', (data) => {
            if (data.performance) {
                callback(data.performance);
            }
        });
    }

    /**
     * 監聽地圖錯誤
     */
    onMapError(callback: (error: any) => void): void {
        this.webSocketService.on('mapError', callback);
    }

    /**
     * 發送地圖互動事件
     */
    sendMapInteraction(interaction: {
        type: 'click' | 'hover' | 'zoom' | 'pan';
        coordinates: [number, number];
        zoom?: number;
        bounds?: MapBounds;
    }): void {
        this.webSocketService.emit('mapInteraction', {
            ...interaction,
            timestamp: Date.now(),
        });
    }

    /**
     * 請求地圖優化建議
     */
    async requestOptimizationSuggestions(
        bounds: MapBounds,
        zoom: number,
    ): Promise<void> {
        await this.requestMapUpdate({
            bounds,
            zoom,
            type: 'statistics', // 使用統計資料來提供優化建議
        });
    }
}

// 單例模式
export const mapWebSocketService = new MapWebSocketService();
export default mapWebSocketService;
