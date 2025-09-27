import React, { useState, useEffect } from 'react';
import { useRealtimeMap } from '../hooks/useRealtimeMap';
import { LoadingIndicator } from './LoadingIndicator';
import { ConnectionStatus } from './ConnectionStatus';

interface RealtimeMapProps {
    onMapUpdate?: (data: any) => void;
    showStatus?: boolean;
    showPerformance?: boolean;
    className?: string;
}

export const RealtimeMap: React.FC<RealtimeMapProps> = ({
    onMapUpdate,
    showStatus = true,
    showPerformance = false,
    className = '',
}) => {
    const {
        mapData,
        isReceivingUpdates,
        lastUpdateTime,
        subscribeToMapUpdates,
        unsubscribeFromMapUpdates,
        clearMapData,
    } = useRealtimeMap();

    const [isSubscribed, setIsSubscribed] = useState(false);

    useEffect(() => {
        // 自動訂閱地圖更新
        subscribeToMapUpdates();
        setIsSubscribed(true);

        return () => {
            unsubscribeFromMapUpdates();
            setIsSubscribed(false);
        };
    }, [subscribeToMapUpdates, unsubscribeFromMapUpdates]);

    useEffect(() => {
        if (mapData && onMapUpdate) {
            onMapUpdate(mapData);
        }
    }, [mapData, onMapUpdate]);

    const handleToggleSubscription = () => {
        if (isSubscribed) {
            unsubscribeFromMapUpdates();
            setIsSubscribed(false);
        } else {
            subscribeToMapUpdates();
            setIsSubscribed(true);
        }
    };

    const formatLastUpdate = (date?: Date) => {
        if (!date) return '從未更新';
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const seconds = Math.floor(diff / 1000);
        
        if (seconds < 60) return `${seconds} 秒前`;
        
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes} 分鐘前`;
        
        const hours = Math.floor(minutes / 60);
        return `${hours} 小時前`;
    };

    return (
        <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-800">
                    即時地圖更新
                </h3>
                
                {showStatus && (
                    <ConnectionStatus showDetails={false} />
                )}
            </div>

            <div className="space-y-4">
                {/* 訂閱控制 */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <button
                            onClick={handleToggleSubscription}
                            className={`px-3 py-1 rounded text-sm font-medium transition-colors ${
                                isSubscribed
                                    ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                    : 'bg-green-100 text-green-700 hover:bg-green-200'
                            }`}
                        >
                            {isSubscribed ? '停止訂閱' : '開始訂閱'}
                        </button>
                        
                        {isReceivingUpdates && (
                            <LoadingIndicator size="sm" text="接收更新中..." />
                        )}
                    </div>

                    <button
                        onClick={clearMapData}
                        className="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200 transition-colors"
                    >
                        清除資料
                    </button>
                </div>

                {/* 更新狀態 */}
                <div className="bg-gray-50 rounded p-3">
                    <div className="flex justify-between items-center text-sm">
                        <span className="text-gray-600">最後更新:</span>
                        <span className="font-medium">
                            {formatLastUpdate(lastUpdateTime)}
                        </span>
                    </div>
                    
                    {mapData && (
                        <div className="mt-2 text-sm text-gray-600">
                            <div>類型: {mapData.type}</div>
                            <div>時間戳: {new Date(mapData.timestamp).toLocaleString()}</div>
                            {mapData.bounds && (
                                <div>
                                    範圍: {mapData.bounds.north.toFixed(4)}, {mapData.bounds.south.toFixed(4)} - 
                                    {mapData.bounds.east.toFixed(4)}, {mapData.bounds.west.toFixed(4)}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* 效能監控 */}
                {showPerformance && (
                    <div className="border-t pt-4">
                        <h4 className="text-sm font-semibold text-gray-700 mb-2">
                            效能指標
                        </h4>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div className="bg-blue-50 p-2 rounded">
                                <div className="text-blue-600 font-medium">響應時間</div>
                                <div className="text-gray-600">
                                    {mapData?.data?.performance?.response_time || 'N/A'}ms
                                </div>
                            </div>
                            <div className="bg-green-50 p-2 rounded">
                                <div className="text-green-600 font-medium">記憶體使用</div>
                                <div className="text-gray-600">
                                    {mapData?.data?.performance?.memory_usage || 'N/A'}MB
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* 資料預覽 */}
                {mapData && (
                    <div className="border-t pt-4">
                        <h4 className="text-sm font-semibold text-gray-700 mb-2">
                            資料預覽
                        </h4>
                        <div className="bg-gray-50 rounded p-3 max-h-40 overflow-y-auto">
                            <pre className="text-xs text-gray-600 whitespace-pre-wrap">
                                {JSON.stringify(mapData.data, null, 2)}
                            </pre>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
