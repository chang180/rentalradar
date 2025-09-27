import React, { useState, useEffect } from 'react';
import webSocketService from '../services/WebSocketService';

interface PerformanceMetrics {
    timestamp: number;
    responseTime: number;
    memoryUsage: number;
    queryCount: number;
    cacheHitRate: number;
    activeConnections: number;
}

interface PerformanceMonitorProps {
    showDetails?: boolean;
    refreshInterval?: number;
    className?: string;
}

export const PerformanceMonitor: React.FC<PerformanceMonitorProps> = ({
    showDetails = false,
    refreshInterval = 5000,
    className = '',
}) => {
    const [metrics, setMetrics] = useState<PerformanceMetrics | null>(null);
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        const handleSystemStatus = (data: any) => {
            if (data.performance) {
                setMetrics({
                    timestamp: Date.now(),
                    responseTime: data.performance.response_time || 0,
                    memoryUsage: data.performance.memory_usage || 0,
                    queryCount: data.performance.query_count || 0,
                    cacheHitRate: data.performance.cache_hit_rate || 0,
                    activeConnections: data.performance.active_connections || 0,
                });
            }
        };

        webSocketService.on('systemStatus', handleSystemStatus);

        return () => {
            webSocketService.off('systemStatus', handleSystemStatus);
        };
    }, []);

    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const formatTime = (ms: number): string => {
        if (ms < 1000) return `${ms}ms`;
        return `${(ms / 1000).toFixed(2)}s`;
    };

    const getPerformanceColor = (value: number, thresholds: { good: number; warning: number }): string => {
        if (value <= thresholds.good) return 'text-green-600';
        if (value <= thresholds.warning) return 'text-yellow-600';
        return 'text-red-600';
    };

    if (!metrics) {
        return null;
    }

    return (
        <div className={`bg-white rounded-lg shadow-md p-4 ${className}`}>
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-sm font-semibold text-gray-700">
                    效能監控
                </h3>
                <button
                    onClick={() => setIsVisible(!isVisible)}
                    className="text-xs text-gray-500 hover:text-gray-700"
                >
                    {isVisible ? '隱藏' : '顯示'}
                </button>
            </div>

            <div className="space-y-2">
                <div className="flex justify-between items-center">
                    <span className="text-xs text-gray-600">響應時間:</span>
                    <span className={`text-xs font-medium ${getPerformanceColor(metrics.responseTime, { good: 100, warning: 500 })}`}>
                        {formatTime(metrics.responseTime)}
                    </span>
                </div>

                <div className="flex justify-between items-center">
                    <span className="text-xs text-gray-600">記憶體使用:</span>
                    <span className={`text-xs font-medium ${getPerformanceColor(metrics.memoryUsage, { good: 50, warning: 100 })}`}>
                        {formatBytes(metrics.memoryUsage * 1024 * 1024)}
                    </span>
                </div>

                <div className="flex justify-between items-center">
                    <span className="text-xs text-gray-600">查詢次數:</span>
                    <span className="text-xs font-medium text-gray-800">
                        {metrics.queryCount}
                    </span>
                </div>

                {showDetails && isVisible && (
                    <>
                        <div className="flex justify-between items-center">
                            <span className="text-xs text-gray-600">快取命中率:</span>
                            <span className={`text-xs font-medium ${getPerformanceColor(100 - metrics.cacheHitRate, { good: 20, warning: 50 })}`}>
                                {metrics.cacheHitRate.toFixed(1)}%
                            </span>
                        </div>

                        <div className="flex justify-between items-center">
                            <span className="text-xs text-gray-600">活躍連接:</span>
                            <span className="text-xs font-medium text-gray-800">
                                {metrics.activeConnections}
                            </span>
                        </div>

                        <div className="text-xs text-gray-500 mt-2">
                            更新時間: {new Date(metrics.timestamp).toLocaleTimeString()}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};
