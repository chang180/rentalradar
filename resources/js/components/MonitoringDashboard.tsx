import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Activity,
    AlertTriangle,
    CheckCircle,
    Clock,
    Cpu,
    Database,
    HardDrive,
    MemoryStick,
    RefreshCw,
    Server,
    XCircle,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';

interface SystemHealth {
    health_score: number;
    status: string;
    core_metrics: {
        cpu_usage: number;
        memory_usage: number;
        disk_usage: number;
        database_connections: number;
        queue_size: number;
        response_time: number;
    };
    app_metrics: {
        active_users: number;
        api_requests: number;
        error_rate: number;
        cache_hit_rate: number;
        database_queries: number;
    };
    alerts: Array<{
        type: string;
        message: string;
        metric: string;
        value: number;
        threshold: number;
    }>;
}

interface PerformanceData {
    slow_queries: Array<any>;
    memory_leaks: Array<any>;
    bottlenecks: Array<any>;
    optimization_opportunities: Array<any>;
    cache_performance: {
        hit_rate: number;
        size: number;
        operations: any;
        efficiency: number;
    };
    database_performance: {
        connections: number;
        slow_queries: number;
        index_usage: any;
        table_sizes: any;
    };
    api_performance: {
        response_times: any;
        throughput: number;
        error_rates: any;
        endpoint_performance: any;
    };
}

interface RepairStatistics {
    total_repairs: number;
    successful_repairs: number;
    failed_repairs: number;
    average_repair_time: number;
    most_common_repairs: Record<string, number>;
    last_repair: string | null;
}

const MonitoringDashboard: React.FC = () => {
    const [systemHealth, setSystemHealth] = useState<SystemHealth | null>(null);
    const [performance, setPerformance] = useState<PerformanceData | null>(
        null,
    );
    const [repairStats, setRepairStats] = useState<RepairStatistics | null>(
        null,
    );
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [lastUpdate, setLastUpdate] = useState<Date | null>(null);

    const fetchDashboardData = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/monitoring/dashboard');
            const data = await response.json();

            if (data.success) {
                setSystemHealth(data.data.system_health);
                setPerformance(data.data.performance);
                setRepairStats(data.data.repair_statistics);
                setLastUpdate(new Date());
                setError(null);
            } else {
                setError(data.message || '獲取監控資料失敗');
            }
        } catch (err) {
            setError('網路錯誤: ' + (err as Error).message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchDashboardData();
        const interval = setInterval(fetchDashboardData, 30000); // 每 30 秒更新
        return () => clearInterval(interval);
    }, []);

    const getHealthStatusColor = (status: string) => {
        switch (status) {
            case 'excellent':
                return 'text-green-600';
            case 'good':
                return 'text-blue-600';
            case 'fair':
                return 'text-yellow-600';
            case 'poor':
                return 'text-orange-600';
            case 'critical':
                return 'text-red-600';
            default:
                return 'text-gray-600';
        }
    };

    const getHealthStatusIcon = (status: string) => {
        switch (status) {
            case 'excellent':
                return <CheckCircle className="h-5 w-5 text-green-600" />;
            case 'good':
                return <CheckCircle className="h-5 w-5 text-blue-600" />;
            case 'fair':
                return <AlertTriangle className="h-5 w-5 text-yellow-600" />;
            case 'poor':
                return <AlertTriangle className="h-5 w-5 text-orange-600" />;
            case 'critical':
                return <XCircle className="h-5 w-5 text-red-600" />;
            default:
                return <Activity className="h-5 w-5 text-gray-600" />;
        }
    };

    const getMetricColor = (value: number, threshold: number) => {
        if (value >= threshold) return 'text-red-600';
        if (value >= threshold * 0.8) return 'text-yellow-600';
        return 'text-green-600';
    };

    const formatBytes = (bytes: number) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(2)} ${units[unitIndex]}`;
    };

    const formatTime = (ms: number) => {
        if (ms < 1000) return `${ms.toFixed(0)}ms`;
        return `${(ms / 1000).toFixed(2)}s`;
    };

    if (loading && !systemHealth) {
        return (
            <div className="flex h-64 items-center justify-center">
                <RefreshCw className="h-8 w-8 animate-spin" />
                <span className="ml-2">載入監控資料中...</span>
            </div>
        );
    }

    if (error) {
        return (
            <Alert className="border-red-200 bg-red-50">
                <XCircle className="h-4 w-4 text-red-600" />
                <AlertDescription className="text-red-800">
                    {error}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-6">
            {/* 標題和刷新按鈕 */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">
                        系統監控儀表板
                    </h1>
                    <p className="mt-1 text-gray-600">
                        最後更新: {lastUpdate?.toLocaleString() || '未知'}
                    </p>
                </div>
                <Button onClick={fetchDashboardData} disabled={loading}>
                    <RefreshCw
                        className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                    />
                    刷新資料
                </Button>
            </div>

            {/* 系統健康狀態 */}
            {systemHealth && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            {getHealthStatusIcon(systemHealth.status)}
                            系統健康狀態
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                            <div className="text-center">
                                <div className="mb-2 text-4xl font-bold">
                                    {systemHealth.health_score}%
                                </div>
                                <div
                                    className={`text-lg font-medium ${getHealthStatusColor(systemHealth.status)}`}
                                >
                                    {systemHealth.status === 'excellent'
                                        ? '優秀'
                                        : systemHealth.status === 'good'
                                          ? '良好'
                                          : systemHealth.status === 'fair'
                                            ? '一般'
                                            : systemHealth.status === 'poor'
                                              ? '較差'
                                              : systemHealth.status ===
                                                  'critical'
                                                ? '嚴重'
                                                : '未知'}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <span>CPU 使用率</span>
                                    <span
                                        className={getMetricColor(
                                            systemHealth.core_metrics.cpu_usage,
                                            80,
                                        )}
                                    >
                                        {systemHealth.core_metrics.cpu_usage}%
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>記憶體使用率</span>
                                    <span
                                        className={getMetricColor(
                                            systemHealth.core_metrics
                                                .memory_usage,
                                            85,
                                        )}
                                    >
                                        {systemHealth.core_metrics.memory_usage}
                                        %
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>磁碟使用率</span>
                                    <span
                                        className={getMetricColor(
                                            systemHealth.core_metrics
                                                .disk_usage,
                                            90,
                                        )}
                                    >
                                        {systemHealth.core_metrics.disk_usage}%
                                    </span>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <span>響應時間</span>
                                    <span
                                        className={getMetricColor(
                                            systemHealth.core_metrics
                                                .response_time,
                                            1000,
                                        )}
                                    >
                                        {formatTime(
                                            systemHealth.core_metrics
                                                .response_time,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>錯誤率</span>
                                    <span
                                        className={getMetricColor(
                                            systemHealth.app_metrics.error_rate,
                                            5,
                                        )}
                                    >
                                        {systemHealth.app_metrics.error_rate}%
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>快取命中率</span>
                                    <span
                                        className={getMetricColor(
                                            100 -
                                                systemHealth.app_metrics
                                                    .cache_hit_rate,
                                            20,
                                        )}
                                    >
                                        {
                                            systemHealth.app_metrics
                                                .cache_hit_rate
                                        }
                                        %
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* 警報 */}
            {systemHealth && systemHealth.alerts.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                            系統警報 ({systemHealth.alerts.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {systemHealth.alerts.map((alert, index) => (
                                <Alert
                                    key={index}
                                    className="border-red-200 bg-red-50"
                                >
                                    <AlertTriangle className="h-4 w-4 text-red-600" />
                                    <AlertDescription>
                                        <div className="flex items-center justify-between">
                                            <span className="text-red-800">
                                                {alert.message}
                                            </span>
                                            <Badge variant="destructive">
                                                {alert.value} /{' '}
                                                {alert.threshold}
                                            </Badge>
                                        </div>
                                    </AlertDescription>
                                </Alert>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* 詳細指標 */}
            <Tabs defaultValue="core" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="core">核心指標</TabsTrigger>
                    <TabsTrigger value="app">應用程式指標</TabsTrigger>
                    <TabsTrigger value="performance">效能分析</TabsTrigger>
                    <TabsTrigger value="repairs">修復統計</TabsTrigger>
                </TabsList>

                <TabsContent value="core" className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Cpu className="h-4 w-4" />
                                    CPU 使用率
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.core_metrics.cpu_usage}%
                                </div>
                                <div className="mt-2 h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        className="h-2 rounded-full bg-blue-600"
                                        style={{
                                            width: `${systemHealth?.core_metrics.cpu_usage}%`,
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <MemoryStick className="h-4 w-4" />
                                    記憶體使用率
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.core_metrics.memory_usage}%
                                </div>
                                <div className="mt-2 h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        className="h-2 rounded-full bg-green-600"
                                        style={{
                                            width: `${systemHealth?.core_metrics.memory_usage}%`,
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <HardDrive className="h-4 w-4" />
                                    磁碟使用率
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.core_metrics.disk_usage}%
                                </div>
                                <div className="mt-2 h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        className="h-2 rounded-full bg-yellow-600"
                                        style={{
                                            width: `${systemHealth?.core_metrics.disk_usage}%`,
                                        }}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Database className="h-4 w-4" />
                                    資料庫連線
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {
                                        systemHealth?.core_metrics
                                            .database_connections
                                    }
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    活躍連線數
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Server className="h-4 w-4" />
                                    佇列大小
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.core_metrics.queue_size}
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    待處理任務
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Clock className="h-4 w-4" />
                                    響應時間
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {formatTime(
                                        systemHealth?.core_metrics
                                            .response_time || 0,
                                    )}
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    平均響應時間
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="app" className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    活躍使用者
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.app_metrics.active_users}
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    過去 30 分鐘
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    API 請求
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.app_metrics.api_requests}
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    總請求數
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    錯誤率
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.app_metrics.error_rate}%
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    錯誤百分比
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    快取命中率
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.app_metrics.cache_hit_rate}%
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    快取效率
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    資料庫查詢
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {systemHealth?.app_metrics.database_queries}
                                </div>
                                <p className="mt-1 text-xs text-gray-600">
                                    查詢次數
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <TabsContent value="performance" className="space-y-4">
                    {performance && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>慢查詢</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-2 text-2xl font-bold">
                                        {performance.slow_queries.length}
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        超過 1 秒的查詢數量
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>記憶體洩漏</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-2 text-2xl font-bold">
                                        {performance.memory_leaks.length}
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        檢測到的記憶體問題
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>效能瓶頸</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-2 text-2xl font-bold">
                                        {performance.bottlenecks.length}
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        系統瓶頸數量
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>優化機會</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-2 text-2xl font-bold">
                                        {
                                            performance
                                                .optimization_opportunities
                                                .length
                                        }
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        可優化項目
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </TabsContent>

                <TabsContent value="repairs" className="space-y-4">
                    {repairStats && (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle>總修復次數</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {repairStats.total_repairs}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>成功修復</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-green-600">
                                        {repairStats.successful_repairs}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>失敗修復</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-red-600">
                                        {repairStats.failed_repairs}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>平均修復時間</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {formatTime(
                                            repairStats.average_repair_time,
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>最後修復</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-sm">
                                        {repairStats.last_repair
                                            ? new Date(
                                                  repairStats.last_repair,
                                              ).toLocaleString()
                                            : '無'}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </TabsContent>
            </Tabs>
        </div>
    );
};

export default MonitoringDashboard;
