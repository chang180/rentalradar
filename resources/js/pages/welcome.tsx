import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { dashboard, login, register } from '@/routes/index';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart3, Brain, MapPin, Shield, Users, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';

interface PublicMapAvailability {
    is_available: boolean;
    remaining_seconds: number;
    used_seconds: number;
    daily_limit_seconds: number;
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const [publicMapAvailability, setPublicMapAvailability] =
        useState<PublicMapAvailability | null>(null);
    const [isCheckingAvailability, setIsCheckingAvailability] = useState(true);

    // 檢查免費試用地圖可用性
    useEffect(() => {
        const checkAvailability = async () => {
            try {
                const response = await fetch('/api/public-map/availability');
                if (response.ok) {
                    const data = await response.json();
                    setPublicMapAvailability(data);
                }
            } catch (error) {
                console.error('檢查免費試用可用性失敗:', error);
            } finally {
                setIsCheckingAvailability(false);
            }
        };

        // 只有在非登入狀態時才檢查
        if (!auth.user) {
            checkAvailability();
        } else {
            setIsCheckingAvailability(false);
        }
    }, [auth.user]);

    return (
        <>
            <Head title="RentalRadar - AI 租屋市場分析平台">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=noto-sans-tc:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>
            <div className="relative min-h-screen overflow-hidden">
                {/* 背景圖片和漸層 */}
                <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900" />
                <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiMwNTk2NjkiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PGNpcmNsZSBjeD0iMzAiIGN5PSIzMCIgcj0iMiIvPjwvZz48L2c+PC9zdmc+')] opacity-40 dark:opacity-20" />

                {/* 浮動幾何圖形 */}
                <div className="absolute top-20 left-10 h-20 w-20 animate-pulse rounded-full bg-blue-200/20 blur-xl dark:bg-blue-400/10" />
                <div className="absolute top-40 right-20 h-32 w-32 animate-pulse rounded-full bg-indigo-200/20 blur-xl delay-1000 dark:bg-indigo-400/10" />
                <div className="absolute bottom-20 left-1/4 h-24 w-24 animate-pulse rounded-full bg-purple-200/20 blur-xl delay-2000 dark:bg-purple-400/10" />
                <div className="absolute right-1/3 bottom-40 h-16 w-16 animate-pulse rounded-full bg-cyan-200/20 blur-xl delay-3000 dark:bg-cyan-400/10" />

                {/* 網格背景 */}
                <div className="absolute inset-0 bg-[linear-gradient(rgba(59,130,246,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(59,130,246,0.05)_1px,transparent_1px)] bg-[size:50px_50px] dark:bg-[linear-gradient(rgba(147,197,253,0.1)_1px,transparent_1px),linear-gradient(90deg,rgba(147,197,253,0.1)_1px,transparent_1px)]" />

                {/* 主要內容容器 */}
                <div className="relative z-10">
                    {/* 導航欄 */}
                    <header className="border-b border-gray-200/50 bg-white/70 shadow-lg shadow-blue-500/5 backdrop-blur-md dark:border-gray-700/50 dark:bg-gray-900/70 dark:shadow-blue-500/10">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="flex h-16 items-center justify-between">
                                <div className="flex items-center">
                                    <div className="flex items-center space-x-2">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600">
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
                                            href={dashboard.url()}
                                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                                        >
                                            開始分析
                                        </Link>
                                    ) : (
                                        <div className="flex items-center space-x-3">
                                            <Link
                                                href={login()}
                                                className="text-sm font-medium text-gray-700 transition-colors hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400"
                                            >
                                                登入
                                            </Link>
                                            <Link
                                                href={register()}
                                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
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
                        <div className="relative text-center">
                            {/* 背景裝飾 */}
                            <div className="absolute -top-20 -left-20 h-40 w-40 animate-pulse rounded-full bg-blue-500/10 blur-3xl" />
                            <div className="absolute -top-10 -right-20 h-32 w-32 animate-pulse rounded-full bg-indigo-500/10 blur-3xl delay-1000" />
                            <div className="absolute -bottom-20 left-1/2 h-48 w-48 -translate-x-1/2 transform animate-pulse rounded-full bg-purple-500/10 blur-3xl delay-2000" />

                            <div className="relative z-10">
                                <h1 className="animate-fade-in-up text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-white">
                                    <span className="animate-fade-in-up block delay-200">
                                        AI 驅動的
                                    </span>
                                    <span className="animate-fade-in-up block bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent delay-400">
                                        租屋市場分析平台
                                    </span>
                                </h1>
                                <p className="animate-fade-in-up mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600 delay-600 dark:text-gray-300">
                                    運用人工智慧技術，整合政府開放資料，為您提供精準的租屋市場洞察。
                                    <br className="hidden sm:block" />
                                    讓每個租屋族都能用數據找到好房子！
                                </p>
                                <div className="animate-fade-in-up mt-10 flex flex-col items-center justify-center gap-x-6 gap-y-4 delay-800 sm:flex-row">
                                    <Link
                                        href={register()}
                                        className="group relative transform rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-base font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                    >
                                        <span className="relative z-10">
                                            立即開始
                                        </span>
                                        <div className="absolute inset-0 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                                    </Link>
                                    {/* 只在非登入狀態且免費試用可用時顯示 */}
                                    {!auth.user &&
                                        !isCheckingAvailability &&
                                        publicMapAvailability?.is_available && (
                                            <Link
                                                href="/public-map"
                                                className="group flex items-center gap-2 text-base leading-6 font-semibold text-gray-900 transition-all duration-300 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                            >
                                                免費試用地圖
                                                <span
                                                    className="transition-transform duration-300 group-hover:translate-x-1"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </Link>
                                        )}
                                    {/* 如果正在檢查可用性或免費試用不可用，顯示載入中或提示 */}
                                    {!auth.user && isCheckingAvailability && (
                                        <div className="flex items-center gap-2 text-base leading-6 font-semibold text-gray-400 dark:text-gray-500">
                                            檢查中...
                                        </div>
                                    )}
                                    {!auth.user &&
                                        !isCheckingAvailability &&
                                        publicMapAvailability &&
                                        !publicMapAvailability.is_available && (
                                            <div className="flex items-center gap-2 text-base leading-6 font-semibold text-gray-400 dark:text-gray-500">
                                                今日免費試用已用完
                                            </div>
                                        )}
                                </div>
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
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-blue-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-blue-100 to-blue-200 transition-transform duration-300 group-hover:scale-110 dark:from-blue-900 dark:to-blue-800">
                                        <Brain className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                        AI 智慧分析
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        運用機器學習演算法，自動清理資料、檢測異常值，提供準確的市場分析。
                                    </p>
                                </div>

                                {/* 互動式地圖 */}
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-green-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-green-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-green-100 to-green-200 transition-transform duration-300 group-hover:scale-110 dark:from-green-900 dark:to-green-800">
                                        <MapPin className="h-6 w-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-green-600 dark:text-white dark:group-hover:text-green-400">
                                        互動式地圖
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        視覺化租金分布，熱力圖分析，讓您直觀了解各區域租屋市場狀況。
                                    </p>
                                </div>

                                {/* 統計分析 */}
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-purple-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-purple-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-purple-100 to-purple-200 transition-transform duration-300 group-hover:scale-110 dark:from-purple-900 dark:to-purple-800">
                                        <BarChart3 className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-purple-600 dark:text-white dark:group-hover:text-purple-400">
                                        深度統計分析
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        趨勢預測、市場洞察，基於歷史資料提供專業的租屋建議。
                                    </p>
                                </div>

                                {/* 使用者回報 */}
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-orange-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-orange-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-orange-100 to-orange-200 transition-transform duration-300 group-hover:scale-110 dark:from-orange-900 dark:to-orange-800">
                                        <Users className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-orange-600 dark:text-white dark:group-hover:text-orange-400">
                                        社群回報系統
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        使用者回報真實租屋資訊，建立信譽評分機制，確保資料品質。
                                    </p>
                                </div>

                                {/* 政府資料整合 */}
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-red-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-red-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-red-100 to-red-200 transition-transform duration-300 group-hover:scale-110 dark:from-red-900 dark:to-red-800">
                                        <Shield className="h-6 w-6 text-red-600 dark:text-red-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-red-600 dark:text-white dark:group-hover:text-red-400">
                                        政府資料整合
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        整合政府開放資料，活化實價登錄資訊，提供最權威的市場數據。
                                    </p>
                                </div>

                                {/* 即時更新 */}
                                <div className="group transform rounded-2xl bg-white/80 p-8 shadow-lg ring-1 ring-gray-200/50 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-yellow-500/10 dark:bg-gray-800/80 dark:ring-gray-700/50 dark:hover:shadow-yellow-500/20">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-r from-yellow-100 to-yellow-200 transition-transform duration-300 group-hover:scale-110 dark:from-yellow-900 dark:to-yellow-800">
                                        <Zap className="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                                    </div>
                                    <h3 className="mt-6 text-lg font-semibold text-gray-900 transition-colors duration-300 group-hover:text-yellow-600 dark:text-white dark:group-hover:text-yellow-400">
                                        即時資料更新
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        每10日自動更新資料，確保您獲得最新的市場資訊和趨勢分析。
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* 技術特色 */}
                        <div className="relative mt-24 overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-8 py-16 text-center">
                            {/* 背景裝飾 */}
                            <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC4xIj48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSIxLjUiLz48L2c+PC9nPjwvc3ZnPg==')] opacity-30" />
                            <div className="absolute -top-10 -left-10 h-40 w-40 animate-pulse rounded-full bg-white/10 blur-2xl" />
                            <div className="absolute -right-10 -bottom-10 h-32 w-32 animate-pulse rounded-full bg-white/10 blur-2xl delay-1000" />

                            <div className="relative z-10">
                                <h2 className="animate-fade-in-up text-3xl font-bold text-white">
                                    技術優勢
                                </h2>
                                <p className="animate-fade-in-up mt-4 text-lg text-blue-100 delay-200">
                                    基於 Laravel 12 + React + AI
                                    的現代化技術架構
                                </p>
                                <div className="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="group animate-fade-in-up text-center delay-300">
                                        <div className="text-3xl font-bold text-white transition-transform duration-300 group-hover:scale-110">
                                            Laravel 12
                                        </div>
                                        <div className="mt-2 text-blue-100">
                                            最新後端框架
                                        </div>
                                    </div>
                                    <div className="group animate-fade-in-up text-center delay-400">
                                        <div className="text-3xl font-bold text-white transition-transform duration-300 group-hover:scale-110">
                                            React 19
                                        </div>
                                        <div className="mt-2 text-blue-100">
                                            現代前端技術
                                        </div>
                                    </div>
                                    <div className="group animate-fade-in-up text-center delay-500">
                                        <div className="text-3xl font-bold text-white transition-transform duration-300 group-hover:scale-110">
                                            AI 驅動
                                        </div>
                                        <div className="mt-2 text-blue-100">
                                            智慧資料分析
                                        </div>
                                    </div>
                                    <div className="group animate-fade-in-up text-center delay-600">
                                        <div className="text-3xl font-bold text-white transition-transform duration-300 group-hover:scale-110">
                                            政府資料
                                        </div>
                                        <div className="mt-2 text-blue-100">
                                            權威資料來源
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* CTA 區域 */}
                        <div className="relative mt-24 text-center">
                            {/* 背景裝飾 */}
                            <div className="absolute inset-0 rounded-3xl bg-gradient-to-r from-blue-50 to-indigo-50 opacity-50 dark:from-gray-800 dark:to-gray-900" />
                            <div className="absolute -top-20 left-1/2 h-64 w-64 -translate-x-1/2 transform animate-pulse rounded-full bg-blue-500/5 blur-3xl" />

                            <div className="relative z-10">
                                {auth.user ? (
                                    <>
                                        <h2 className="animate-fade-in-up text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                            歡迎回來，{auth.user.name}！
                                        </h2>
                                        <p className="animate-fade-in-up mt-4 text-lg text-gray-600 delay-200 dark:text-gray-300">
                                            準備開始您的智慧租屋分析之旅
                                        </p>
                                        <div className="animate-fade-in-up mt-8 flex flex-col items-center justify-center gap-x-6 gap-y-4 delay-400 sm:flex-row">
                                            <Link
                                                href={dashboard.url()}
                                                className="group relative transform rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-lg font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl"
                                            >
                                                <span className="relative z-10">
                                                    進入 Dashboard
                                                </span>
                                                <div className="absolute inset-0 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                                            </Link>
                                            <Link
                                                href="/map"
                                                className="group flex items-center gap-2 text-lg font-semibold text-gray-900 transition-all duration-300 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                            >
                                                查看地圖分析
                                                <span
                                                    className="transition-transform duration-300 group-hover:translate-x-1"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </Link>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <h2 className="animate-fade-in-up text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                            準備開始您的智慧租屋之旅？
                                        </h2>
                                        <p className="animate-fade-in-up mt-4 text-lg text-gray-600 delay-200 dark:text-gray-300">
                                            立即註冊，體驗 AI
                                            驅動的租屋市場分析平台
                                        </p>
                                        <div className="animate-fade-in-up mt-8 flex flex-col items-center justify-center gap-x-6 gap-y-4 delay-400 sm:flex-row">
                                            <Link
                                                href={register()}
                                                className="group relative transform rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-lg font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:from-blue-700 hover:to-indigo-700 hover:shadow-xl"
                                            >
                                                <span className="relative z-10">
                                                    免費註冊
                                                </span>
                                                <div className="absolute inset-0 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                                            </Link>
                                            <Link
                                                href={login()}
                                                className="group flex items-center gap-2 text-lg font-semibold text-gray-900 transition-all duration-300 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                            >
                                                已有帳號？登入
                                                <span
                                                    className="transition-transform duration-300 group-hover:translate-x-1"
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </Link>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </main>

                    {/* 頁尾 */}
                    <footer className="relative mt-24 border-t border-gray-200/50 bg-white/80 backdrop-blur-sm dark:border-gray-700/50 dark:bg-gray-900/80">
                        {/* 背景裝飾 */}
                        <div className="absolute inset-0 bg-gradient-to-t from-gray-50 to-transparent dark:from-gray-800 dark:to-transparent" />
                        <div className="absolute top-0 left-1/2 h-32 w-32 -translate-x-1/2 transform rounded-full bg-blue-500/5 blur-2xl" />

                        <div className="relative z-10 mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                            <div className="text-center">
                                <div className="animate-fade-in-up flex items-center justify-center space-x-2">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 shadow-lg">
                                        <MapPin className="h-5 w-5 text-white" />
                                    </div>
                                    <span className="text-xl font-bold text-gray-900 dark:text-white">
                                        RentalRadar
                                    </span>
                                </div>
                                <p className="animate-fade-in-up mt-4 text-gray-600 delay-200 dark:text-gray-300">
                                    AI-Powered Rental Market Analytics Platform
                                </p>
                                <p className="animate-fade-in-up mt-2 text-sm text-gray-500 delay-400 dark:text-gray-400">
                                    © 2025 RentalRadar.
                                    讓每個租屋族都能用數據找到好房子！
                                </p>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}
