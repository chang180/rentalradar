import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MapPin, BarChart3, Home, TrendingUp, Users, Building } from 'lucide-react';
import { PerformanceMonitor } from '@/components/PerformanceMonitor';

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

                    <Link
                        href="/performance"
                        className="group bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 hover:border-green-300 dark:hover:border-green-600 transition-all duration-200 hover:shadow-lg"
                    >
                        <div className="flex items-center space-x-4">
                            <div className="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition-colors">
                                <BarChart3 className="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h3 className="font-semibold text-gray-900 dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400">
                                    效能監控
                                </h3>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    系統效能和資料分析
                                </p>
                            </div>
                        </div>
                    </Link>

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
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">活躍房源</p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">1,234</p>
                            </div>
                            <Building className="w-8 h-8 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">平均租金</p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">$25,000</p>
                            </div>
                            <TrendingUp className="w-8 h-8 text-green-600 dark:text-green-400" />
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">使用者</p>
                                <p className="text-2xl font-bold text-gray-900 dark:text-white">5,678</p>
                            </div>
                            <Users className="w-8 h-8 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                </div>

                {/* 效能監控區塊 */}
                <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center space-x-3">
                            <div className="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                                <BarChart3 className="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">系統效能監控</h2>
                                <p className="text-sm text-gray-600 dark:text-gray-400">即時系統效能指標</p>
                            </div>
                        </div>
                        <Link
                            href="/performance"
                            className="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium"
                        >
                            查看詳細 →
                        </Link>
                    </div>
                    
                    <PerformanceMonitor showDetails={false} refreshInterval={10000} />
                </div>
            </div>
        </AppLayout>
    );
}
