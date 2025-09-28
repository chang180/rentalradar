import React, { useEffect, useState } from 'react';
import { ConnectionStatus } from '../components/ConnectionStatus';
import { LoadingIndicator } from '../components/LoadingIndicator';
import {
    MultiMetricChart,
    PerformanceChart,
} from '../components/PerformanceChartSimple';
import { PerformanceMonitor } from '../components/PerformanceMonitor';
import { useWebSocket } from '../hooks/useWebSocket';
import { PerformanceUtils } from '../utils/PerformanceUtils';
import { useAdminCheck } from '../hooks/useAdmin';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Shield } from 'lucide-react';

interface DashboardMetrics {
    timestamp: number;
    responseTime: number;
    memoryUsage: number;
    queryCount: number;
    cacheHitRate: number;
    activeConnections: number;
    errorRate: number;
    throughput: number;
}

interface ErrorLog {
    id: string;
    timestamp: number;
    level: 'error' | 'warning' | 'info';
    message: string;
    stack?: string;
    userAgent?: string;
    url?: string;
    userId?: string;
}

interface UserBehavior {
    userId: string;
    sessionId: string;
    timestamp: number;
    action: string;
    page: string;
    duration: number;
    metadata?: any;
}

export const PerformanceDashboard: React.FC = () => {
    const isAdmin = useAdminCheck();
    const { isConnected } = useWebSocket();
    const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
    const [errorLogs, setErrorLogs] = useState<ErrorLog[]>([]);
    const [userBehaviors, setUserBehaviors] = useState<UserBehavior[]>([]);
    const [performanceHistory, setPerformanceHistory] = useState<
        DashboardMetrics[]
    >([]);
    const [isLoading, setIsLoading] = useState(true);
    const [timeRange, setTimeRange] = useState<'1h' | '6h' | '24h' | '7d'>(
        '1h',
    );
    const [selectedTab, setSelectedTab] = useState<
        'overview' | 'errors' | 'users' | 'performance'
    >('overview');

    // 載入效能資料
    useEffect(() => {
        const loadPerformanceData = async () => {
            try {
                setIsLoading(true);

                // 嘗試從 API 載入真實資料
                try {
                    const response = await fetch(
                        `/api/dashboard/overview?timeRange=${timeRange}`,
                        {
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        },
                    );

                    if (response.ok) {
                        const data = await response.json();
                        setMetrics(data.metrics);
                        setErrorLogs(data.errorLogs || []);
                        setUserBehaviors(data.userBehaviors || []);
                        setPerformanceHistory(data.performanceHistory || []);
                        return;
                    }
                } catch (apiError) {
                    console.warn('API 載入失敗，使用模擬資料:', apiError);
                }

                // 如果 API 失敗，使用模擬資料作為後備
                const mockMetrics: DashboardMetrics = {
                    timestamp: Date.now(),
                    responseTime: Math.random() * 200 + 50,
                    memoryUsage: Math.random() * 100 + 20,
                    queryCount: Math.floor(Math.random() * 50 + 10),
                    cacheHitRate: Math.random() * 40 + 60,
                    activeConnections: Math.floor(Math.random() * 100 + 10),
                    errorRate: Math.random() * 5,
                    throughput: Math.random() * 1000 + 500,
                };

                setMetrics(mockMetrics);
                PerformanceUtils.recordMetrics(mockMetrics);

                // 生成歷史資料用於圖表
                const historyData: DashboardMetrics[] = [];
                for (let i = 0; i < 20; i++) {
                    const timestamp = Date.now() - i * 5 * 60 * 1000; // 每5分鐘一個資料點
                    historyData.push({
                        timestamp,
                        responseTime: Math.random() * 200 + 50,
                        memoryUsage: Math.random() * 100 + 20,
                        queryCount: Math.floor(Math.random() * 50 + 10),
                        cacheHitRate: Math.random() * 40 + 60,
                        activeConnections: Math.floor(Math.random() * 100 + 10),
                        errorRate: Math.random() * 5,
                        throughput: Math.random() * 1000 + 500,
                    });
                }
                setPerformanceHistory(historyData.reverse());
            } catch (error) {
                console.error('載入效能資料失敗:', error);
            } finally {
                setIsLoading(false);
            }
        };

        loadPerformanceData();

        // 定期更新資料
        const interval = setInterval(loadPerformanceData, 5000);
        return () => clearInterval(interval);
    }, []);

    // 載入錯誤日誌
    useEffect(() => {
        const loadErrorLogs = async () => {
            try {
                // 模擬錯誤日誌資料
                const mockErrors: ErrorLog[] = [
                    {
                        id: '1',
                        timestamp: Date.now() - 300000,
                        level: 'error',
                        message: 'Database connection timeout',
                        stack: 'Error: Connection timeout\n    at Database.connect()',
                        userAgent: 'Mozilla/5.0...',
                        url: '/api/map/properties',
                    },
                    {
                        id: '2',
                        timestamp: Date.now() - 600000,
                        level: 'warning',
                        message: 'High memory usage detected',
                        userAgent: 'Mozilla/5.0...',
                        url: '/api/map/clusters',
                    },
                ];

                setErrorLogs(mockErrors);
            } catch (error) {
                console.error('載入錯誤日誌失敗:', error);
            }
        };

        loadErrorLogs();
    }, []);

    // 載入使用者行為資料
    useEffect(() => {
        const loadUserBehaviors = async () => {
            try {
                // 模擬使用者行為資料
                const mockBehaviors: UserBehavior[] = [
                    {
                        userId: 'user-1',
                        sessionId: 'session-1',
                        timestamp: Date.now() - 120000,
                        action: 'map_view',
                        page: '/map',
                        duration: 45000,
                        metadata: {
                            zoom: 12,
                            bounds: {
                                north: 25.1,
                                south: 25.0,
                                east: 121.6,
                                west: 121.5,
                            },
                        },
                    },
                    {
                        userId: 'user-2',
                        sessionId: 'session-2',
                        timestamp: Date.now() - 300000,
                        action: 'property_search',
                        page: '/search',
                        duration: 30000,
                        metadata: { filters: { priceRange: [10000, 30000] } },
                    },
                ];

                setUserBehaviors(mockBehaviors);
            } catch (error) {
                console.error('載入使用者行為資料失敗:', error);
            }
        };

        loadUserBehaviors();
    }, []);

    const formatTime = (timestamp: number): string => {
        return new Date(timestamp).toLocaleString();
    };

    const formatDuration = (ms: number): string => {
        const seconds = Math.floor(ms / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (hours > 0) return `${hours}h ${minutes % 60}m`;
        if (minutes > 0) return `${minutes}m ${seconds % 60}s`;
        return `${seconds}s`;
    };

    const getErrorLevelColor = (level: string): string => {
        switch (level) {
            case 'error':
                return 'text-red-600 bg-red-50 border-red-200';
            case 'warning':
                return 'text-yellow-600 bg-yellow-50 border-yellow-200';
            case 'info':
                return 'text-blue-600 bg-blue-50 border-blue-200';
            default:
                return 'text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-700';
        }
    };

    const getErrorLevelIcon = (level: string): string => {
        switch (level) {
            case 'error':
                return '❌';
            case 'warning':
                return '⚠️';
            case 'info':
                return 'ℹ️';
            default:
                return '📝';
        }
    };

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-700 dark:bg-gray-900">
                <LoadingIndicator size="lg" text="載入效能監控儀表板..." />
            </div>
        );
    }

    if (!isAdmin) {
        return (
            <div className="container mx-auto py-8">
                <div className="text-center">
                    <Shield className="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 dark:text-gray-400" />
                    <h2 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-gray-100">
                        權限不足
                    </h2>
                    <p className="mt-2 text-gray-600 dark:text-gray-300 dark:text-gray-400">
                        您沒有權限存取此頁面。
                    </p>
                    <Link 
                        href="/dashboard" 
                        className="mt-4 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:text-gray-100 dark:text-gray-400 dark:hover:text-gray-100 transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        返回儀表板
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-700 dark:bg-gray-900">
            {/* 標題列 */}
            <div className="border-b bg-white dark:bg-gray-800 shadow-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="py-4">
                        <div className="flex items-center gap-4 mb-4">
                            <Link 
                                href="/dashboard" 
                                className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:text-gray-100 dark:text-gray-400 dark:hover:text-gray-100 transition-colors"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                返回儀表板
                            </Link>
                        </div>
                        <div className="flex h-16 items-center justify-between">
                            <div className="flex items-center">
                                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100 dark:text-gray-100">
                                    效能監控儀表板
                                </h1>
                                <div className="ml-4">
                                    <ConnectionStatus showDetails={false} />
                                </div>
                            </div>

                            <div className="flex items-center space-x-4">
                            <select
                                value={timeRange}
                                onChange={(e) =>
                                    setTimeRange(e.target.value as any)
                                }
                                className="rounded-md border border-gray-300 px-3 py-1 text-sm"
                            >
                                <option value="1h">過去 1 小時</option>
                                <option value="6h">過去 6 小時</option>
                                <option value="24h">過去 24 小時</option>
                                <option value="7d">過去 7 天</option>
                            </select>

                            <button
                                onClick={() => window.location.reload()}
                                className="rounded-md bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700"
                            >
                                重新整理
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* 標籤導航 */}
            <div className="border-b bg-white dark:bg-gray-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <nav className="flex space-x-8">
                        {[
                            { id: 'overview', label: '總覽', icon: '📊' },
                            { id: 'performance', label: '效能', icon: '⚡' },
                            { id: 'errors', label: '錯誤', icon: '🐛' },
                            { id: 'users', label: '使用者', icon: '👥' },
                        ].map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setSelectedTab(tab.id as any)}
                                className={`border-b-2 px-1 py-4 text-sm font-medium ${
                                    selectedTab === tab.id
                                        ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                }`}
                            >
                                <span className="mr-2">{tab.icon}</span>
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>
            </div>

            {/* 主要內容 */}
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {selectedTab === 'overview' && (
                    <div className="space-y-6">
                        {/* 關鍵指標卡片 */}
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                            <span className="text-sm text-blue-600">
                                                ⚡
                                            </span>
                                        </div>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400 dark:text-gray-400">
                                            響應時間
                                        </p>
                                        <p className="text-2xl font-semibold text-gray-900 dark:text-gray-100 dark:text-gray-100">
                                            {metrics?.responseTime?.toFixed(
                                                0,
                                            ) || '0'}
                                            ms
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                            <span className="text-sm text-green-600">
                                                💾
                                            </span>
                                        </div>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            記憶體使用
                                        </p>
                                        <p className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                            {metrics?.memoryUsage?.toFixed(1) ||
                                                '0'}
                                            MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100">
                                            <span className="text-sm text-yellow-600">
                                                🔗
                                            </span>
                                        </div>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            活躍連接
                                        </p>
                                        <p className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                            {metrics?.activeConnections || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                                            <span className="text-sm text-red-600">
                                                ❌
                                            </span>
                                        </div>
                                    </div>
                                    <div className="ml-4">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            錯誤率
                                        </p>
                                        <p className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                            {metrics?.errorRate?.toFixed(2) ||
                                                '0.00'}
                                            %
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 效能監控組件 */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <PerformanceMonitor showDetails={true} />
                            <div className="rounded-lg bg-white dark:bg-gray-800 p-6 shadow">
                                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    系統狀態
                                </h3>
                                <div className="space-y-3">
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-600 dark:text-gray-300">
                                            WebSocket 連接
                                        </span>
                                        <span
                                            className={`text-sm font-medium ${isConnected ? 'text-green-600' : 'text-red-600'}`}
                                        >
                                            {isConnected ? '已連接' : '已斷線'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-600 dark:text-gray-300">
                                            快取命中率
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {metrics?.cacheHitRate?.toFixed(
                                                1,
                                            ) || '0.0'}
                                            %
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-600 dark:text-gray-300">
                                            查詢次數
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {metrics?.queryCount || 0}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-600 dark:text-gray-300">
                                            吞吐量
                                        </span>
                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {metrics?.throughput?.toFixed(0) ||
                                                '0'}{' '}
                                            req/min
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {selectedTab === 'errors' && (
                    <div className="space-y-6">
                        <div className="rounded-lg bg-white dark:bg-gray-800 shadow">
                            <div className="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    錯誤日誌
                                </h3>
                            </div>
                            <div className="divide-y divide-gray-200">
                                {errorLogs.map((error) => (
                                    <div key={error.id} className="p-6">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-start space-x-3">
                                                <span className="text-lg">
                                                    {getErrorLevelIcon(
                                                        error.level,
                                                    )}
                                                </span>
                                                <div className="flex-1">
                                                    <div className="flex items-center space-x-2">
                                                        <span
                                                            className={`rounded px-2 py-1 text-xs font-medium ${getErrorLevelColor(error.level)}`}
                                                        >
                                                            {error.level.toUpperCase()}
                                                        </span>
                                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                                            {formatTime(
                                                                error.timestamp,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                                        {error.message}
                                                    </p>
                                                    {error.url && (
                                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                            URL: {error.url}
                                                        </p>
                                                    )}
                                                    {error.stack && (
                                                        <details className="mt-2">
                                                            <summary className="cursor-pointer text-xs text-gray-500 dark:text-gray-400">
                                                                查看堆疊追蹤
                                                            </summary>
                                                            <pre className="mt-2 overflow-x-auto rounded bg-gray-50 dark:bg-gray-700 p-2 text-xs text-gray-600 dark:text-gray-300">
                                                                {error.stack}
                                                            </pre>
                                                        </details>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {selectedTab === 'users' && (
                    <div className="space-y-6">
                        <div className="rounded-lg bg-white dark:bg-gray-800 shadow">
                            <div className="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    使用者行為分析
                                </h3>
                            </div>
                            <div className="divide-y divide-gray-200">
                                {userBehaviors.map((behavior, index) => (
                                    <div key={index} className="p-6">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                                    <span className="text-sm text-blue-600">
                                                        👤
                                                    </span>
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        使用者 {behavior.userId}
                                                    </p>
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        {behavior.action} -{' '}
                                                        {behavior.page}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm text-gray-900 dark:text-gray-100">
                                                    {formatDuration(
                                                        behavior.duration,
                                                    )}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {formatTime(
                                                        behavior.timestamp,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        {behavior.metadata && (
                                            <div className="mt-2 rounded bg-gray-50 dark:bg-gray-700 p-2 text-xs text-gray-600 dark:text-gray-300">
                                                <pre>
                                                    {JSON.stringify(
                                                        behavior.metadata,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {selectedTab === 'performance' && (
                    <div className="space-y-6">
                        <PerformanceMonitor showDetails={true} />

                        {/* 響應時間趨勢 */}
                        <PerformanceChart
                            data={performanceHistory}
                            metric="responseTime"
                            title="響應時間趨勢"
                            color="#3B82F6"
                            type="line"
                        />

                        {/* 記憶體使用趨勢 */}
                        <PerformanceChart
                            data={performanceHistory}
                            metric="memoryUsage"
                            title="記憶體使用趨勢"
                            color="#10B981"
                            type="area"
                        />

                        {/* 多指標綜合圖表 */}
                        <MultiMetricChart
                            data={performanceHistory}
                            metrics={[
                                {
                                    key: 'responseTime',
                                    title: '響應時間 (ms)',
                                    color: '#3B82F6',
                                },
                                {
                                    key: 'memoryUsage',
                                    title: '記憶體使用 (MB)',
                                    color: '#10B981',
                                },
                                {
                                    key: 'queryCount',
                                    title: '查詢次數',
                                    color: '#F59E0B',
                                },
                                {
                                    key: 'activeConnections',
                                    title: '活躍連接',
                                    color: '#EF4444',
                                },
                            ]}
                            type="line"
                            height={400}
                        />
                    </div>
                )}
            </div>
        </div>
    );
};

export default PerformanceDashboard;
