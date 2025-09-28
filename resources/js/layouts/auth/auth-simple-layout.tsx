import { home } from '@/routes/index';
import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="relative min-h-svh overflow-hidden">
            {/* 背景設計 */}
            <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-indigo-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900" />
            <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiMwNTk2NjkiIGZpbGwtb3BhY2l0eT0iMC4wNSI+PGNpcmNsZSBjeD0iMzAiIGN5PSIzMCIgcj0iMiIvPjwvZz48L2c+PC9zdmc+')] opacity-40 dark:opacity-20" />

            {/* 浮動幾何圖形 */}
            <div className="absolute top-20 left-10 h-20 w-20 animate-pulse rounded-full bg-blue-200/20 blur-xl dark:bg-blue-400/10" />
            <div className="absolute top-40 right-20 h-32 w-32 animate-pulse rounded-full bg-indigo-200/20 blur-xl delay-1000 dark:bg-indigo-400/10" />
            <div className="absolute bottom-20 left-1/4 h-24 w-24 animate-pulse rounded-full bg-purple-200/20 blur-xl delay-2000 dark:bg-purple-400/10" />

            {/* 網格背景 */}
            <div className="absolute inset-0 bg-[linear-gradient(rgba(59,130,246,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(59,130,246,0.05)_1px,transparent_1px)] bg-[size:50px_50px] dark:bg-[linear-gradient(rgba(147,197,253,0.1)_1px,transparent_1px),linear-gradient(90deg,rgba(147,197,253,0.1)_1px,transparent_1px)]" />

            <div className="relative z-10 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
                <div className="w-full max-w-md">
                    <div className="rounded-2xl border border-gray-200/50 bg-white/80 p-8 shadow-xl backdrop-blur-sm dark:border-gray-700/50 dark:bg-gray-800/80">
                        <div className="flex flex-col gap-8">
                            <div className="flex flex-col items-center gap-4">
                                <Link
                                    href={home()}
                                    className="group flex flex-col items-center gap-3 font-medium transition-all duration-300 hover:scale-105"
                                >
                                    <div className="flex items-center space-x-2">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 shadow-lg transition-shadow duration-300 group-hover:shadow-xl">
                                            <MapPin className="h-6 w-6 text-white" />
                                        </div>
                                        <span className="text-2xl font-bold text-gray-900 dark:text-white">
                                            RentalRadar
                                        </span>
                                    </div>
                                    <span className="sr-only">{title}</span>
                                </Link>

                                <div className="space-y-3 text-center">
                                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {title}
                                    </h1>
                                    <p className="text-center text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                        {description}
                                    </p>
                                </div>
                            </div>
                            {children}
                        </div>
                    </div>

                    {/* 頁尾說明 */}
                    <div className="mt-8 text-center">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            AI 驅動的租屋市場分析平台
                        </p>
                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            讓每個租屋族都能用數據找到好房子
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
