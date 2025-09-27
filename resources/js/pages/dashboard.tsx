import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MapPin, Home, TrendingUp, Users, Search, Filter, Star } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: '首頁',
        href: dashboard().url,
    },
];

export default function Dashboard() {
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
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

                    <div className="group bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 transition-all duration-200 hover:shadow-lg">
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
                    </div>

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
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">平均租金</p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">$25,000</p>
                                <p className="text-xs text-green-600 dark:text-green-400 mt-1">↗ +2.3% 較上月</p>
                            </div>
                            <TrendingUp className="w-8 h-8 text-green-600 dark:text-green-400" />
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">熱門區域</p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">信義區</p>
                                <p className="text-xs text-blue-600 dark:text-blue-400 mt-1">最受歡迎租屋區域</p>
                            </div>
                            <Star className="w-8 h-8 text-yellow-600 dark:text-yellow-400" />
                        </div>
                    </div>
                </div>

                {/* 快速操作 */}
                <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">快速操作</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <button className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <Filter className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">進階篩選</span>
                        </button>
                        <button className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <Search className="w-5 h-5 text-green-600 dark:text-green-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">智慧搜尋</span>
                        </button>
                        <button className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <TrendingUp className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">價格預測</span>
                        </button>
                        <button className="flex items-center space-x-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <Users className="w-5 h-5 text-orange-600 dark:text-orange-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">社群分享</span>
                        </button>
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
