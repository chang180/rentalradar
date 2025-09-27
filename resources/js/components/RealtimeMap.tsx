import React, { useEffect, useState } from 'react';
import { useRealtimeMap } from '../hooks/useRealtimeMap';
import { ConnectionStatus } from './ConnectionStatus';
import { LoadingIndicator } from './LoadingIndicator';

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
        <div className={`rounded-lg bg-white p-4 shadow-md ${className}`}>
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-800">
                    即時地圖更新
                </h3>

                {showStatus && <ConnectionStatus showDetails={false} />}
            </div>

            <div className="space-y-4">
                {/* 訂閱控制 */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <button
                            onClick={handleToggleSubscription}
                            className={`rounded px-3 py-1 text-sm font-medium transition-colors ${
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
                        className="rounded bg-gray-100 px-3 py-1 text-sm text-gray-700 transition-colors hover:bg-gray-200"
                    >
                        清除資料
                    </button>
                </div>

                {/* 更新狀態 */}
                <div className="rounded bg-gray-50 p-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600">最後更新:</span>
                        <span className="font-medium">
                            {formatLastUpdate(lastUpdateTime)}
                        </span>
                    </div>

                    {mapData && (
                        <div className="mt-2 text-sm text-gray-600">
                            <div>類型: {mapData.type}</div>
                            <div>
                                時間戳:{' '}
                                {new Date(mapData.timestamp).toLocaleString()}
                            </div>
                            {mapData.bounds && (
                                <div>
                                    範圍: {mapData.bounds.north.toFixed(4)},{' '}
                                    {mapData.bounds.south.toFixed(4)} -
                                    {mapData.bounds.east.toFixed(4)},{' '}
                                    {mapData.bounds.west.toFixed(4)}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* 效能監控 */}
                {showPerformance && (
                    <div className="border-t pt-4">
                        <h4 className="mb-2 text-sm font-semibold text-gray-700">
                            效能指標
                        </h4>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div className="rounded bg-blue-50 p-2">
                                <div className="font-medium text-blue-600">
                                    響應時間
                                </div>
                                <div className="text-gray-600">
                                    {mapData?.data?.performance
                                        ?.response_time || 'N/A'}
                                    ms
                                </div>
                            </div>
                            <div className="rounded bg-green-50 p-2">
                                <div className="font-medium text-green-600">
                                    記憶體使用
                                </div>
                                <div className="text-gray-600">
                                    {mapData?.data?.performance?.memory_usage ||
                                        'N/A'}
                                    MB
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* 資料預覽 */}
                {mapData && (
                    <div className="border-t pt-4">
                        <h4 className="mb-2 text-sm font-semibold text-gray-700">
                            資料預覽
                        </h4>
                        <div className="max-h-40 overflow-y-auto rounded bg-gray-50 p-3">
                            <pre className="text-xs whitespace-pre-wrap text-gray-600">
                                {JSON.stringify(mapData.data, null, 2)}
                            </pre>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
