import AdvancedTrendAnalysis from '@/components/analysis/AdvancedTrendAnalysis';
import InteractiveHeatmap from '@/components/analysis/InteractiveHeatmap';
import InvestmentInsightsComponent from '@/components/analysis/InvestmentInsights';
import PriceComparisonChart from '@/components/analysis/PriceComparisonChart';
import PriceDistributionChart from '@/components/analysis/PriceDistributionChart';
import ReportSummary from '@/components/analysis/ReportSummary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useMarketAnalysis } from '@/hooks/use-market-analysis';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { type MarketAnalysisFilters } from '@/types/analysis';
import { Head } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Loader2,
    RefreshCw,
    Sparkles,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useState, useCallback, useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: '儀表板',
        href: dashboard.url(),
    },
    {
        title: '市場分析',
        href: '/analysis',
    },
];

const timeRanges = [
    { value: '3m', label: '過去 3 個月' },
    { value: '6m', label: '過去 6 個月' },
    { value: '12m', label: '過去 12 個月' },
    { value: '24m', label: '過去 24 個月' },
];

export default function MarketAnalysisDashboard() {
    const {
        data,
        loading,
        error,
        filters,
        refresh,
        report,
        reportLoading,
        reportError,
        generateReport,
    } = useMarketAnalysis();

    const [selectedCity, setSelectedCity] = useState<string>('');
    const [cities, setCities] = useState<any[]>([]);
    const [districts, setDistricts] = useState<{ district: string; property_count: number }[]>([]);

    // 獲取縣市列表
    const fetchCities = useCallback(async () => {
        try {
            const response = await fetch('/api/map/cities');
            const data = await response.json();
            if (data.success) {
                setCities(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch cities:', err);
        }
    }, []);

    // 獲取行政區列表
    const fetchDistricts = useCallback(async (city: string) => {
        if (!city) {
            setDistricts([]);
            return;
        }
        try {
            const response = await fetch(
                `/api/map/districts?city=${encodeURIComponent(city)}`,
            );
            const data = await response.json();
            if (data.success) {
                setDistricts(data.data);
            }
        } catch (err) {
            console.error('Failed to fetch districts:', err);
        }
    }, []);

    // 處理縣市選擇變更
    const handleCityChange = useCallback(async (city: string) => {
        if (city === 'all') {
            setSelectedCity('');
            setDistricts([]);
            void handleFilterChange({ district: undefined });
            return;
        }
        
        setSelectedCity(city);
        await fetchDistricts(city);
        
        // 選擇縣市時顯示該縣市的全部資料，預設為「全區」
        // 用戶可以進一步選擇特定行政區
        void handleFilterChange({ district: undefined });
    }, [fetchDistricts]);

    // 初始化載入縣市列表
    useEffect(() => {
        void fetchCities();
    }, [fetchCities]);

    const handleFilterChange = async (override: MarketAnalysisFilters) => {
        await refresh(override);
    };

    const summary = data?.trends.summary;
    const meta = data?.meta;
    const investment = data?.investment;

    const summaryCards = [
        {
            label: '平均租金',
            value: summary?.current_average
                ? `$${summary.current_average.toLocaleString()}`
                : 'N/A',
            change: summary?.month_over_month_change,
            icon: TrendingUp,
        },
        {
            label: '交易量中位數',
            value: summary?.current_volume
                ? summary.current_volume.toLocaleString()
                : 'N/A',
            change: summary?.volume_trend,
            icon: Activity,
        },
        {
            label: '年度變化',
            value:
                summary?.year_over_year_change !== null &&
                summary?.year_over_year_change !== undefined
                    ? `${summary.year_over_year_change.toFixed(2)}%`
                    : 'N/A',
            badge: '年增率',
            icon: BarChart3,
        },
        {
            label: '信心指數',
            value: investment
                ? `${Math.round(investment.confidence * 100)}%`
                : 'N/A',
            icon: Sparkles,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="市場分析" />
            
            {/* 全頁面 Loading 覆蓋層 */}
            {loading && !data && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm dark:bg-gray-900/80">
                    <div className="flex flex-col items-center space-y-4 rounded-lg bg-white p-8 shadow-lg dark:bg-gray-800">
                        <Loader2 className="h-12 w-12 animate-spin text-blue-600" />
                        <div className="text-center">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                正在分析市場數據
                            </h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                首次載入需要一些時間來處理複雜的市場分析...
                            </p>
                            <div className="mt-3 flex items-center justify-center space-x-2 text-xs text-gray-400">
                                <div className="h-2 w-2 animate-bounce rounded-full bg-blue-500"></div>
                                <div className="h-2 w-2 animate-bounce rounded-full bg-blue-500" style={{ animationDelay: '0.1s' }}></div>
                                <div className="h-2 w-2 animate-bounce rounded-full bg-blue-500" style={{ animationDelay: '0.2s' }}></div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-xl p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            市場分析儀表板
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            深入分析租賃市場趨勢、價格動態和投資信號。
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Select
                            value={filters.time_range ?? '12m'}
                            onValueChange={(value) => {
                                void handleFilterChange({ time_range: value });
                            }}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue
                                    aria-label="time-range"
                                    placeholder="選擇時間範圍"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {timeRanges.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={selectedCity || 'all'}
                            onValueChange={(value) => {
                                void handleCityChange(value);
                            }}
                        >
                            <SelectTrigger className="w-32">
                                <SelectValue placeholder="選擇縣市" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">全部縣市</SelectItem>
                                {cities.map((city) => (
                                    <SelectItem key={city.city} value={city.city}>
                                        {city.city}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.district ?? 'all'}
                            onValueChange={(value) => {
                                if (value === 'all') {
                                    void handleFilterChange({
                                        district: undefined,
                                    });
                                    return;
                                }
                                void handleFilterChange({ district: value });
                            }}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="所有區域" />
                            </SelectTrigger>
                            <SelectContent>
                                {!selectedCity && <SelectItem value="all">所有區域</SelectItem>}
                                {selectedCity && <SelectItem value="all">全區</SelectItem>}
                                {districts.map((district) => (
                                    <SelectItem key={district.district} value={district.district}>
                                        {district.district} ({district.property_count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                void handleFilterChange({});
                            }}
                            disabled={loading}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                            />
                            重新整理
                        </Button>

                        <Button
                            type="button"
                            onClick={() => {
                                void generateReport();
                            }}
                            disabled={reportLoading}
                        >
                            <Sparkles
                                className={`h-4 w-4 ${reportLoading ? 'animate-pulse' : ''}`}
                            />
                            生成報告
                        </Button>
                    </div>
                </div>

                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>無法載入分析資料</AlertTitle>
                        <AlertDescription className="flex items-center justify-between">
                            <span>{error}</span>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => refresh()}
                                className="ml-4"
                            >
                                <RefreshCw className="h-4 w-4" />
                                <span className="ml-2">重試</span>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <section>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {loading || !data
                            ? summaryCards.map((card) => (
                                  <div
                                      key={card.label}
                                      className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                                  >
                                      <Skeleton className="h-4 w-24" />
                                      <Skeleton className="mt-4 h-8 w-32" />
                                      <Skeleton className="mt-2 h-3 w-20" />
                                  </div>
                              ))
                            : summaryCards.map((card) => {
                                  const Icon = card.icon;
                                  return (
                                      <div
                                          key={card.label}
                                          className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                                      >
                                          <div className="flex items-center justify-between">
                                              <div>
                                                  <p className="text-sm text-gray-500 dark:text-gray-400">
                                                      {card.label}
                                                  </p>
                                                  <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                                                      {card.value}
                                                  </p>
                                              </div>
                                              <div className="rounded-full bg-blue-50 p-2 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                                  <Icon className="h-5 w-5" />
                                              </div>
                                          </div>
                                          {card.change !== undefined &&
                                              card.change !== null && (
                                                  <p
                                                      className={`mt-3 text-xs font-medium ${(card.change ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}
                                                  >
                                                      {card.change >= 0
                                                          ? '+'
                                                          : ''}
                                                      {card.change.toFixed(2)}%
                                                      較上期
                                                  </p>
                                              )}
                                      </div>
                                  );
                              })}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <div className="flex flex-col items-center justify-center space-y-4 h-72">
                                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        正在分析趨勢數據
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        這可能需要幾秒鐘...
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <AdvancedTrendAnalysis
                                data={data.trends.timeseries}
                                forecast={data.trends.forecast}
                            />
                        )}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <Skeleton className="h-72 w-full" />
                        ) : (
                            <div className="flex h-full flex-col justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                        關鍵市場統計
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        當前市場健康狀況快照。
                                    </p>
                                </div>
                                <dl className="mt-6 space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                    <div className="flex items-center justify-between">
                                        <dt>生成時間</dt>
                                        <dd>
                                            {meta?.generated_at
                                                ? new Date(
                                                      meta.generated_at,
                                                  ).toLocaleString()
                                                : 'N/A'}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>分析物件數</dt>
                                        <dd>
                                            {typeof meta?.property_count ===
                                            'number'
                                                ? meta.property_count.toLocaleString()
                                                : 'N/A'}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>市場信號</dt>
                                        <dd>
                                            {investment?.signals.bullish
                                                .length ?? 0}{' '}
                                            看漲 ·{' '}
                                            {investment?.signals.bearish
                                                .length ?? 0}{' '}
                                            看跌
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>追蹤熱點</dt>
                                        <dd>
                                            {investment?.hotspots.length ?? 0}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        )}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <div className="flex flex-col items-center justify-center space-y-4 h-72">
                                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        正在分析區域價格比較
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        計算各區域價格差異...
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <PriceComparisonChart
                                data={data.price_comparison.districts}
                            />
                        )}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <div className="flex flex-col items-center justify-center space-y-4 h-72">
                                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        正在分析價格分布
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        計算統計數據中...
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <PriceDistributionChart
                                data={data.price_comparison.distribution}
                            />
                        )}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <div className="flex flex-col items-center justify-center space-y-4 h-64">
                                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        正在分析投資洞察
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        計算AI投資建議...
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <InvestmentInsightsComponent
                                data={data.investment}
                            />
                        )}
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <div className="flex flex-col items-center justify-center space-y-4 h-64">
                                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                                <div className="text-center">
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        正在生成互動熱力圖
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        處理地理空間數據...
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <InteractiveHeatmap
                                data={data.multi_dimensional.spatial}
                            />
                        )}
                    </div>
                </section>

                <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        自動化市場報告
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        生成包含建議和可分享洞察的執行摘要。
                    </p>
                    <div className="mt-6">
                        <ReportSummary
                            report={report}
                            isLoading={reportLoading}
                            error={reportError}
                        />
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
