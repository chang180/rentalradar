import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AppShell } from '@/components/app-shell';
import { AppContent } from '@/components/app-content';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { Clock, ArrowLeft, User } from 'lucide-react';
import RentalMap from '../components/rental-map';

interface MapProps {
    is_public?: boolean;
    remaining_seconds?: number;
    ip_usage_data?: {
        used_seconds: number;
        start_time: string;
        last_access: string;
    };
}

export default function Map({ is_public = false, remaining_seconds = 1800, ip_usage_data }: MapProps) {
    const [timeLeft, setTimeLeft] = useState(remaining_seconds);
    const [isActive, setIsActive] = useState(true);

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
            navigator.sendBeacon('/api/public-map/session-end', JSON.stringify({
                session_end: true,
                timestamp: new Date().toISOString(),
            }));
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

    // 如果是公開模式，使用簡化的布局
    if (is_public) {
        return (
            <>
                <Head title="公開地圖 - RentalRadar" />
                <div className="h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
                    {/* 頂部導航欄 */}
                    <header className="flex-shrink-0 bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                        <div className="px-4 sm:px-6 lg:px-8">
                            <div className="flex justify-between items-center h-16">
                                <div className="flex items-center space-x-4">
                                    <Link
                                        href="/"
                                        className="flex items-center space-x-2 text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors"
                                    >
                                        <ArrowLeft className="h-5 w-5" />
                                        <span>返回首頁</span>
                                    </Link>
                                </div>
                                
                                <div className="flex items-center space-x-4">
                                    {/* 使用時間顯示 */}
                                    <div className="flex items-center space-x-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <Clock className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        <div className="text-sm">
                                            <div className="text-blue-600 dark:text-blue-400 font-medium">
                                                {isActive ? `剩餘 ${formatTime(timeLeft)}` : '時間已用完'}
                                            </div>
                                            <div className="text-blue-500 dark:text-blue-500 text-xs">
                                                今日已用 {usedMinutes.toString().padStart(2, '0')}:{usedSeconds.toString().padStart(2, '0')}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <Link
                                        href="/register"
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                                    >
                                        註冊完整功能
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* 使用限制提示 */}
                    <div className="flex-shrink-0 px-4 sm:px-6 lg:px-8 py-4">
                        <div className="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <div className="flex items-center space-x-2">
                                <User className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                                <div>
                                    <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        免費試用模式
                                    </h3>
                                    <p className="text-sm text-yellow-700 dark:text-yellow-300">
                                        您正在使用免費試用版本，每日限時 30 分鐘。註冊帳號可獲得完整功能。
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 主要內容 */}
                    <main className="flex-1 flex flex-col min-h-0">
                        {/* 標題區域 */}
                        <div className="flex-shrink-0 px-4 sm:px-6 lg:px-8 py-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
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
                        <div className="flex-1 bg-white dark:bg-gray-800 min-h-0">
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
                <AppContent variant="sidebar" className="overflow-hidden flex flex-col">
                    <AppSidebarHeader breadcrumbs={[
                        { title: '首頁', href: '/dashboard' },
                        { title: '地圖', href: '/map' }
                    ]} />
                    
                    <div className="flex-1 flex flex-col">
                        {/* 標題區域 */}
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    租屋地圖
                                </h1>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    透過互動式地圖探索台北市租屋市場
                                </p>
                            </div>
                        </div>

                        {/* 地圖容器 - 佔用剩餘空間 */}
                        <div className="flex-1 bg-white dark:bg-gray-800">
                            <RentalMap />
                        </div>
                    </div>
                </AppContent>
            </AppShell>
        </>
    );
}