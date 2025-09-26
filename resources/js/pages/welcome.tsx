import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { MapPin, BarChart3, Brain, Users, Shield, Zap } from 'lucide-react';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="RentalRadar - AI 租屋市場分析平台">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=noto-sans-tc:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>
            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                {/* 導航欄 */}
                <header className="border-b border-gray-200 bg-white/80 backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900/80">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 items-center justify-between">
                            <div className="flex items-center">
                                <div className="flex items-center space-x-2">
                                    <div className="h-8 w-8 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center">
                                        <MapPin className="h-5 w-5 text-white" />
                                    </div>
                                    <span className="text-xl font-bold text-gray-900 dark:text-white">
                                        RentalRadar
                                    </span>
                                </div>
                            </div>
                            <nav className="flex items-center space-x-4">
                                <AppearanceToggleDropdown />
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                    >
                                        進入儀表板
                                    </Link>
                                ) : (
                                    <div className="flex items-center space-x-3">
                                        <Link
                                            href={login()}
                                            className="text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors"
                                        >
                                            登入
                                        </Link>
                                        <Link
                                            href={register()}
                                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                        >
                                            免費註冊
                                        </Link>
                                    </div>
                                )}
                            </nav>
                        </div>
                    </div>
                </header>

                {/* 主要內容 */}
                <main className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                    {/* 英雄區域 */}
                    <div className="text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                            <span className="block">AI 驅動的</span>
                            <span className="block bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                                租屋市場分析平台
                            </span>
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600 dark:text-gray-300">
                            運用人工智慧技術，整合政府開放資料，為您提供精準的租屋市場洞察。
                            讓每個租屋族都能用數據找到好房子！
                        </p>
                        <div className="mt-10 flex items-center justify-center gap-x-6">
                            <Link
                                href={register()}
                                className="rounded-lg bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors"
                            >
                                立即開始
                            </Link>
                            <Link
                                href="#features"
                                className="text-base font-semibold leading-6 text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400 transition-colors"
                            >
                                了解更多 <span aria-hidden="true">→</span>
                            </Link>
                        </div>
                    </div>

                    {/* 特色功能 */}
                    <div id="features" className="mt-24">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                核心功能
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                基於 AI 技術的智慧租屋分析解決方案
                            </p>
                        </div>

                        <div className="mt-16 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            {/* AI 資料分析 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                                    <Brain className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    AI 智慧分析
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    運用機器學習演算法，自動清理資料、檢測異常值，提供準確的市場分析。
                                </p>
                            </div>

                            {/* 互動式地圖 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                                    <MapPin className="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    互動式地圖
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    視覺化租金分布，熱力圖分析，讓您直觀了解各區域租屋市場狀況。
                                </p>
                            </div>

                            {/* 統計分析 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900">
                                    <BarChart3 className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    深度統計分析
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    趨勢預測、市場洞察，基於歷史資料提供專業的租屋建議。
                                </p>
                            </div>

                            {/* 使用者回報 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900">
                                    <Users className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    社群回報系統
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    使用者回報真實租屋資訊，建立信譽評分機制，確保資料品質。
                                </p>
                            </div>

                            {/* 政府資料整合 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900">
                                    <Shield className="h-6 w-6 text-red-600 dark:text-red-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    政府資料整合
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    整合政府開放資料，活化實價登錄資訊，提供最權威的市場數據。
                                </p>
                            </div>

                            {/* 即時更新 */}
                            <div className="rounded-2xl bg-white p-8 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900">
                                    <Zap className="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                                </div>
                                <h3 className="mt-6 text-lg font-semibold text-gray-900 dark:text-white">
                                    即時資料更新
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    每10日自動更新資料，確保您獲得最新的市場資訊和趨勢分析。
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* 技術特色 */}
                    <div className="mt-24 rounded-3xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-16 text-center">
                        <h2 className="text-3xl font-bold text-white">
                            技術優勢
                        </h2>
                        <p className="mt-4 text-lg text-blue-100">
                            基於 Laravel 12 + React + AI 的現代化技術架構
                        </p>
                        <div className="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="text-center">
                                <div className="text-3xl font-bold text-white">Laravel 12</div>
                                <div className="mt-2 text-blue-100">最新後端框架</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-white">React 19</div>
                                <div className="mt-2 text-blue-100">現代前端技術</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-white">AI 驅動</div>
                                <div className="mt-2 text-blue-100">智慧資料分析</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-white">政府資料</div>
                                <div className="mt-2 text-blue-100">權威資料來源</div>
                            </div>
                        </div>
                    </div>

                    {/* CTA 區域 */}
                    <div className="mt-24 text-center">
                        <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            準備開始您的智慧租屋之旅？
                        </h2>
                        <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                            立即註冊，體驗 AI 驅動的租屋市場分析平台
                        </p>
                        <div className="mt-8 flex items-center justify-center gap-x-6">
                            <Link
                                href={register()}
                                className="rounded-lg bg-blue-600 px-8 py-3 text-lg font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors"
                            >
                                免費註冊
                            </Link>
                            <Link
                                href={login()}
                                className="text-lg font-semibold text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400 transition-colors"
                            >
                                已有帳號？登入
                            </Link>
                        </div>
                    </div>
                </main>

                {/* 頁尾 */}
                <footer className="mt-24 border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <div className="flex items-center justify-center space-x-2">
                                <div className="h-8 w-8 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-center">
                                    <MapPin className="h-5 w-5 text-white" />
                                </div>
                                <span className="text-xl font-bold text-gray-900 dark:text-white">
                                    RentalRadar
                                </span>
                            </div>
                            <p className="mt-4 text-gray-600 dark:text-gray-300">
                                AI-Powered Rental Market Analytics Platform
                            </p>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                © 2025 RentalRadar. 讓每個租屋族都能用數據找到好房子！
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
