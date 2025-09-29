import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, User, Loader2, AlertCircle, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import RentalMap from '../components/rental-map';
import { LoadingIndicator } from '../components/LoadingIndicator';

interface MapProps {
    is_public?: boolean;
    remaining_seconds?: number;
    ip_usage_data?: {
        used_seconds: number;
        start_time: string;
        last_access: string;
    };
}

export default function Map({
    is_public = false,
    remaining_seconds = 1800,
    ip_usage_data,
}: MapProps) {
    const [timeLeft, setTimeLeft] = useState(remaining_seconds);
    const [isActive, setIsActive] = useState(true);
    const [isInitialLoading, setIsInitialLoading] = useState(true);
    const [loadingError, setLoadingError] = useState<string | null>(null);
    const [retryCount, setRetryCount] = useState(0);
    const [statistics, setStatistics] = useState<any>(null);
    const [properties, setProperties] = useState<any[]>([]);

    // 檢查地圖資料載入狀態
    useEffect(() => {
        const checkMapDataStatus = async () => {
            try {
                // 檢查 Redis 快取狀態
                const response = await fetch('/api/map/status');
                const data = await response.json();
                
                // 檢查是否有實際的資料
                const hasData = data.data && (
                    data.data.property_count > 0 || 
                    data.data.aggregated_count > 0 ||
                    data.data.has_aggregated_data
                );
                
                if (data.success && hasData) {
                    // 有資料且成功
                    setIsInitialLoading(false);
                    setLoadingError(null);
                } else if (data.success && !hasData) {
                    // 成功但沒有資料
                    setIsInitialLoading(false);
                    setLoadingError('目前沒有可用的租屋資料，請稍後再試或聯繫管理員');
                } else {
                    // 資料未準備好
                    setLoadingError('地圖資料尚未準備完成，正在載入中...');
                    // 如果資料未準備好，等待 3 秒後重試，但最多重試 5 次
                    if (retryCount < 5) {
                        setTimeout(() => {
                            setRetryCount(prev => prev + 1);
                            checkMapDataStatus();
                        }, 3000);
                    } else {
                        // 重試次數過多，顯示錯誤
                        setIsInitialLoading(false);
                        setLoadingError('地圖資料載入失敗，請重新整理頁面或聯繫管理員');
                    }
                }
            } catch (error) {
                console.error('Map data status check failed:', error);
                setLoadingError('無法連接到地圖服務，請稍後再試');
                // 5 秒後重試，但最多重試 3 次
                if (retryCount < 3) {
                    setTimeout(() => {
                        setRetryCount(prev => prev + 1);
                        checkMapDataStatus();
                    }, 5000);
                } else {
                    // 重試次數過多，顯示錯誤
                    setIsInitialLoading(false);
                    setLoadingError('無法連接到地圖服務，請檢查網路連接或聯繫管理員');
                }
            }
        };

        // 初始載入檢查
        checkMapDataStatus();
    }, [retryCount]);

    useEffect(() => {
        if (!is_public || !isActive || timeLeft <= 0) return;

        const timer = setInterval(() => {
            setTimeLeft((prev) => {
                if (prev <= 1) {
                    setIsActive(false);
                    // 時間到了重導向到首頁
                    alert('您的免費試用時間已用完，請註冊帳號以獲得完整功能。');
                    window.location.href = '/';
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, [is_public, isActive, timeLeft]);

    // 監聽頁面離開事件，通知後端會話結束
    useEffect(() => {
        if (!is_public) return;

        const handleBeforeUnload = () => {
            // 發送會話結束通知
            navigator.sendBeacon(
                '/api/public-map/session-end',
                JSON.stringify({
                    session_end: true,
                    timestamp: new Date().toISOString(),
                }),
            );
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [is_public]);

    const formatTime = (seconds: number): string => {
        const totalSeconds = Math.floor(seconds); // 取整數秒數
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    };

    // 已用時間 = 30分鐘 - 剩餘時間
    const totalUsedSeconds = Math.max(0, 1800 - timeLeft);
    const usedMinutes = Math.floor(totalUsedSeconds / 60);
    const usedSeconds = Math.floor(totalUsedSeconds % 60);

    // 重試載入地圖資料
    const handleRetry = () => {
        setRetryCount(0);
        setIsInitialLoading(true);
        setLoadingError(null);
    };

    // 載入狀態或錯誤狀態 UI
    if (isInitialLoading || loadingError) {
        const isError = loadingError && !isInitialLoading;
        const isNoData = loadingError && loadingError.includes('沒有可用的租屋資料');
        
        return (
            <>
                <Head title={isError ? "載入失敗 - RentalRadar" : "載入中 - RentalRadar"} />
                <div className="flex h-screen flex-col bg-gray-50 dark:bg-gray-900">
                    {/* 頂部導航欄 */}
                    <header className="flex-shrink-0 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div className="px-4 sm:px-6 lg:px-8">
                            <div className="flex h-16 items-center justify-between">
                                <div className="flex items-center space-x-4">
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-2 text-gray-600 transition-colors hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400"
                                    >
                                        <ArrowLeft className="h-5 w-5" />
                                        <span>返回首頁</span>
                                    </Link>
                                </div>
                                <div className="flex items-center space-x-4">
                                    <div className={`flex items-center space-x-2 rounded-lg px-3 py-2 ${
                                        isError 
                                            ? 'bg-red-50 dark:bg-red-900/20' 
                                            : 'bg-blue-50 dark:bg-blue-900/20'
                                    }`}>
                                        {isInitialLoading ? (
                                            <Loader2 className="h-4 w-4 animate-spin text-blue-600 dark:text-blue-400" />
                                        ) : (
                                            <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                        )}
                                        <span className={`text-sm ${
                                            isError 
                                                ? 'text-red-600 dark:text-red-400' 
                                                : 'text-blue-600 dark:text-blue-400'
                                        }`}>
                                            {isInitialLoading ? '載入中...' : '載入失敗'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* 載入內容 */}
                    <main className="flex min-h-0 flex-1 flex-col items-center justify-center">
                        <div className="text-center">
                            <div className="mb-6">
                                {isInitialLoading ? (
                                    <Loader2 className="mx-auto h-16 w-16 animate-spin text-blue-600 dark:text-blue-400" />
                                ) : isNoData ? (
                                    <div className="mx-auto h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                        <AlertCircle className="h-8 w-8 text-gray-400 dark:text-gray-500" />
                                    </div>
                                ) : (
                                    <AlertCircle className="mx-auto h-16 w-16 text-red-500 dark:text-red-400" />
                                )}
                            </div>
                            <h2 className="mb-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {isInitialLoading 
                                    ? '正在載入地圖資料'
                                    : isNoData 
                                        ? '暫無可用資料'
                                        : '載入失敗'
                                }
                            </h2>
                            <p className="mb-4 text-gray-600 dark:text-gray-400">
                                {loadingError || '正在準備地圖資料，請稍候...'}
                            </p>
                            {retryCount > 0 && isInitialLoading && (
                                <p className="mb-4 text-sm text-gray-500 dark:text-gray-500">
                                    重試次數: {retryCount}
                                </p>
                            )}
                            <div className="flex items-center justify-center space-x-4">
                                <button
                                    onClick={handleRetry}
                                    className="flex items-center space-x-2 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    <span>重新載入</span>
                                </button>
                                {isNoData && (
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-2 rounded-lg bg-gray-600 px-4 py-2 text-white hover:bg-gray-700"
                                    >
                                        <ArrowLeft className="h-4 w-4" />
                                        <span>返回首頁</span>
                                    </Link>
                                )}
                            </div>
                        </div>
                    </main>
                </div>
            </>
        );
    }

    // 如果是公開模式，使用簡化的布局
    if (is_public) {
        return (
            <>
                <Head title="公開地圖 - RentalRadar" />
                <div className="flex h-screen flex-col bg-gray-50 dark:bg-gray-900">
                    {/* 頂部導航欄 */}
                    <header className="flex-shrink-0 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div className="px-4 sm:px-6 lg:px-8">
                            <div className="flex h-16 items-center justify-between">
                                <div className="flex items-center space-x-4">
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-2 text-gray-600 transition-colors hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400"
                                    >
                                        <ArrowLeft className="h-5 w-5" />
                                        <span>返回首頁</span>
                                    </Link>
                                </div>

                                <div className="flex items-center space-x-4">
                                    {/* 使用時間顯示 */}
                                    <div className="flex items-center space-x-2 rounded-lg bg-blue-50 px-3 py-2 dark:bg-blue-900/20">
                                        <Clock className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        <div className="text-sm">
                                            <div className="font-medium text-blue-600 dark:text-blue-400">
                                                {isActive
                                                    ? `剩餘 ${formatTime(timeLeft)}`
                                                    : '時間已用完'}
                                            </div>
                                            <div className="text-xs text-blue-500 dark:text-blue-500">
                                                今日已用{' '}
                                                {usedMinutes
                                                    .toString()
                                                    .padStart(2, '0')}
                                                :
                                                {usedSeconds
                                                    .toString()
                                                    .padStart(2, '0')}
                                            </div>
                                        </div>
                                    </div>

                                    <Link
                                        href="/register"
                                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                                    >
                                        註冊完整功能
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* 使用限制提示 */}
                    <div className="flex-shrink-0 px-4 py-4 sm:px-6 lg:px-8">
                        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                            <div className="flex items-center space-x-2">
                                <User className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                                <div>
                                    <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        免費試用模式
                                    </h3>
                                    <p className="text-sm text-yellow-700 dark:text-yellow-300">
                                        您正在使用免費試用版本，每日限時 30
                                        分鐘。註冊帳號可獲得完整功能。
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 主要內容 */}
                    <main className="flex min-h-0 flex-1 flex-col">
                        {/* 標題區域 */}
                        <div className="flex-shrink-0 border-b border-gray-200 bg-white px-4 py-4 sm:px-6 lg:px-8 dark:border-gray-700 dark:bg-gray-800">
                            <div className="w-full">
                                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    租屋地圖
                                </h1>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    透過互動式地圖探索台北市租屋市場
                                </p>
                            </div>
                        </div>

                        {/* 地圖容器 - 佔用剩餘空間 */}
                        <div className="min-h-0 flex-1 bg-white dark:bg-gray-800">
                            <RentalMap />
                        </div>
                    </main>
                </div>
            </>
        );
    }

    // 原本的認證模式布局
    return (
        <>
            <Head title="RentalRadar - 租屋地圖分析" />
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="flex flex-col overflow-hidden"
                >
                    <AppSidebarHeader
                        breadcrumbs={[
                            { title: '首頁', href: '/dashboard' },
                            { title: '地圖', href: '/map' },
                        ]}
                    />

                    <div className="flex flex-1 flex-col">
                        {/* 標題區域與統計概覽 */}
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        台灣租屋市場地圖
                                    </h1>
                                    <p className="mt-2 text-base text-gray-600 dark:text-gray-400">
                                        探索全台租屋市場熱點，發現最適合的租屋區域
                                    </p>
                                </div>
                                {/* 統計概覽移到右側 */}
                                <div className="hidden md:flex items-center gap-6 text-base">
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
                                            {statistics?.total_properties?.toLocaleString() || 0}
                                        </div>
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            總租屋數
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                            {statistics?.cities ? Object.keys(statistics.cities).length : 0}
                                        </div>
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            涵蓋縣市
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                            {statistics?.avg_rent_per_ping
                                                ? Math.round(statistics.avg_rent_per_ping).toLocaleString()
                                                : 0}
                                        </div>
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            平均每坪租金
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 載入狀態提示 */}
                        {isInitialLoading && (
                            <div className="border-b border-gray-200 bg-yellow-50 px-6 py-3 dark:border-gray-700 dark:bg-yellow-900/20">
                                <div className="flex items-center space-x-2">
                                    <Loader2 className="h-4 w-4 animate-spin text-yellow-600 dark:text-yellow-400" />
                                    <span className="text-sm text-yellow-800 dark:text-yellow-200">
                                        {loadingError || '正在載入地圖資料，請稍候...'}
                                    </span>
                                    {retryCount > 0 && (
                                        <span className="text-xs text-yellow-600 dark:text-yellow-400">
                                            (重試 {retryCount} 次)
                                        </span>
                                    )}
                                    <button
                                        onClick={handleRetry}
                                        className="ml-2 flex items-center space-x-1 rounded bg-yellow-600 px-2 py-1 text-xs text-white hover:bg-yellow-700"
                                    >
                                        <RefreshCw className="h-3 w-3" />
                                        <span>重試</span>
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* 地圖容器 - 佔用剩餘空間 */}
                        <div className="flex-1 bg-white dark:bg-gray-800">
                            <RentalMap onStatsUpdate={(stats, props) => {
                                // 更新統計數據用於標題區域顯示
                                setStatistics(stats);
                                setProperties(props);
                            }} />
                        </div>
                    </div>
                </AppContent>
            </AppShell>
        </>
    );
}
