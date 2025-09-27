import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MapPin, Home, TrendingUp, Users, Search, Filter, Star, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: '首頁',
        href: dashboard.url(),
    },
];

interface DashboardStats {
    overview: {
        total_properties: number;
        recent_properties: number;
        time_range: string;
    };
    rent_statistics: {
        average_rent: number;
        average_total_rent: number;
        min_rent: number;
        max_rent: number;
        price_change_percent: number;
        price_change_direction: 'up' | 'down';
    };
    popular_districts: Array<{
        name: string;
        property_count: number;
        average_rent: number;
        average_area: number;
        rent_per_sqm: number | null;
    }>;
    top_district_rent_per_sqm: number | null;
    building_types: Array<{
        type: string;
        count: number;
        average_rent: number;
    }>;
    last_updated: string;
}

export default function Dashboard() {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                setLoading(true);
                const response = await axios.get('/api/dashboard/statistics');
                if (response.data.success) {
                    setStats(response.data.data);
                } else {
                    setError('獲取數據失敗');
                }
            } catch (err) {
                console.error('Dashboard stats error:', err);
                setError('無法載入統計數據');
            } finally {
                setLoading(false);
            }
        };

        fetchStats();
    }, []);

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('zh-TW', {
            style: 'currency',
            currency: 'TWD',
            minimumFractionDigits: 0,
        }).format(price);
    };

    const formatChange = (percent: number, direction: 'up' | 'down') => {
        const sign = direction === 'up' ? '+' : '';
        return `${sign}${percent.toFixed(1)}%`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RentalRadar - 租屋雷達首頁" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* 歡迎區塊 */}
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 rounded-xl p-6 border border-blue-200/50 dark:border-gray-600">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                歡迎使用 RentalRadar
                            </h1>
                            <p className="text-gray-600 dark:text-gray-300">
                                AI 驅動的租屋市場分析平台，幫您找到最適合的租屋選擇
                            </p>
                        </div>
                        <div className="hidden md:block">
                            <div className="w-16 h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full flex items-center justify-center">
                                <Home className="w-8 h-8 text-white" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* 功能卡片 */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Link
                        href="/map"
                        className="group bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 transition-all duration-200 hover:shadow-lg"
                    >
                        <div className="flex items-center space-x-4">
                            <div className="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                                <MapPin className="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                    地圖分析
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    查看租屋熱點和價格分布
                                </p>
                            </div>
                        </div>
                    </Link>

                    {/* 智慧搜尋功能暫時隱藏 */}
                    {/* <div className="group bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 transition-all duration-200 hover:shadow-lg">
                        <div className="flex items-center space-x-4">
                            <div className="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                                <Search className="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400">
                                    智慧搜尋
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    AI 驅動的租屋條件篩選
                                </p>
                            </div>
                        </div>
                    </div> */}

                    <div className="group bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 hover:border-purple-300 dark:hover:border-purple-600 transition-all duration-200 hover:shadow-lg">
                        <div className="flex items-center space-x-4">
                            <div className="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition-colors">
                                <TrendingUp className="w-6 h-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400">
                                    市場趨勢
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    AI 預測租屋市場走向
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 統計資訊 */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {loading ? (
                        <div className="col-span-full flex items-center justify-center py-8">
                            <Loader2 className="w-6 h-6 animate-spin text-blue-600" />
                            <span className="ml-2 text-gray-600 dark:text-gray-400">載入統計數據中...</span>
                        </div>
                    ) : error ? (
                        <div className="col-span-full flex items-center justify-center py-8">
                            <div className="text-center">
                                <p className="text-red-600 dark:text-red-400 mb-2">載入失敗</p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">{error}</p>
                            </div>
                        </div>
                    ) : stats ? (
                        <>
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">最熱門區域每坪租金</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.top_district_rent_per_sqm ? `${stats.top_district_rent_per_sqm.toLocaleString()} 元/坪` : '無數據'}
                                        </p>
                                        <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                            {stats.popular_districts[0]?.name || '無數據'} ({stats.popular_districts[0]?.property_count || 0} 筆資料)
                                        </p>
                                    </div>
                                    <TrendingUp className="w-8 h-8 text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>

                            <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">平均租金</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {formatPrice(stats.rent_statistics.average_rent)}
                                        </p>
                                        <p className={`text-xs mt-1 ${
                                            stats.rent_statistics.price_change_direction === 'up' 
                                                ? 'text-green-600 dark:text-green-400' 
                                                : 'text-red-600 dark:text-red-400'
                                        }`}>
                                            {stats.rent_statistics.price_change_direction === 'up' ? '↗' : '↘'} {formatChange(stats.rent_statistics.price_change_percent, stats.rent_statistics.price_change_direction)} 較上月
                                        </p>
                                    </div>
                                    <Star className="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
                                </div>
                            </div>

                            <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">總租屋數</p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {stats.overview.total_properties.toLocaleString()}
                                        </p>
                                        <p className="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                            最近30天新增 {stats.overview.recent_properties} 筆
                                        </p>
                                    </div>
                                    <Home className="w-8 h-8 text-purple-600 dark:text-purple-400" />
                                </div>
                            </div>

                            <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">租金範圍</p>
                                        <p className="text-lg font-bold text-gray-900 dark:text-white">
                                            {formatPrice(stats.rent_statistics.min_rent)} - {formatPrice(stats.rent_statistics.max_rent)}
                                        </p>
                                        <p className="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                            最低到最高租金
                                        </p>
                                    </div>
                                    <Search className="w-8 h-8 text-orange-600 dark:text-orange-400" />
                                </div>
                            </div>
                        </>
                    ) : null}
                </div>

                {/* 快速操作 */}
                <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">快速操作</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Link
                            href="/map"
                            className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <Filter className="w-5 h-5 text-blue-600 dark:text-blue-400 group-hover:text-blue-700 dark:group-hover:text-blue-300" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400">地圖篩選</span>
                        </Link>
                        <Link
                            href="/analysis"
                            className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <TrendingUp className="w-5 h-5 text-green-600 dark:text-green-400 group-hover:text-green-700 dark:group-hover:text-green-300" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400">市場分析</span>
                        </Link>
                        <Link
                            href="/performance"
                            className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
                        >
                            <TrendingUp className="w-5 h-5 text-purple-600 dark:text-purple-400 group-hover:text-purple-700 dark:group-hover:text-purple-300" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400">效能監控</span>
                        </Link>
                        {/* 社群分享功能暫時 comment 掉 */}
                        {/* <button className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <Users className="w-5 h-5 text-orange-600 dark:text-orange-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">社群分享</span>
                        </button> */}
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
