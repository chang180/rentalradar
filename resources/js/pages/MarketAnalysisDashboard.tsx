import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import TrendChart from '@/components/analysis/TrendChart';
import PriceComparisonChart from '@/components/analysis/PriceComparisonChart';
import PriceDistributionChart from '@/components/analysis/PriceDistributionChart';
import HotspotList from '@/components/analysis/HotspotList';
import ReportSummary from '@/components/analysis/ReportSummary';
import { useMarketAnalysis } from '@/hooks/use-market-analysis';
import type { BreadcrumbItem } from '@/types';
import { type MarketAnalysisFilters } from '@/types/analysis';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Activity, BarChart3, RefreshCw, Sparkles, TrendingUp } from 'lucide-react';
import { dashboard } from '@/routes';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Market Analysis',
        href: '/analysis',
    },
];

const timeRanges = [
    { value: '3m', label: 'Last 3 months' },
    { value: '6m', label: 'Last 6 months' },
    { value: '12m', label: 'Last 12 months' },
    { value: '24m', label: 'Last 24 months' },
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

    const districts = useMemo(() => {
        if (!data) {
            return [] as string[];
        }

        return data.price_comparison.districts
            .map((district) => district.district)
            .filter((value, index, array) => array.indexOf(value) === index)
            .sort();
    }, [data]);

    const handleFilterChange = async (override: MarketAnalysisFilters) => {
        await refresh(override);
    };

    const summary = data?.trends.summary;
    const meta = data?.meta;
    const investment = data?.investment;

    const summaryCards = [
        {
            label: 'Average Rent',
            value: summary?.current_average ? `$${summary.current_average.toLocaleString()}` : 'N/A',
            change: summary?.month_over_month_change,
            icon: TrendingUp,
        },
        {
            label: 'Median Volume',
            value: summary?.current_volume ? summary.current_volume.toLocaleString() : 'N/A',
            change: summary?.volume_trend,
            icon: Activity,
        },
        {
            label: 'Year over Year',
            value: summary?.year_over_year_change !== null && summary?.year_over_year_change !== undefined
                ? `${summary.year_over_year_change.toFixed(2)}%`
                : 'N/A',
            badge: 'YoY change',
            icon: BarChart3,
        },
        {
            label: 'Confidence',
            value: investment ? `${Math.round(investment.confidence * 100)}%` : 'N/A',
            icon: Sparkles,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Market Analysis" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-xl p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Market Analysis Dashboard</h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Deep dive into rental market trends, pricing dynamics, and investment signals.
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
                                <SelectValue aria-label="time-range" placeholder="Select range" />
                            </SelectTrigger>
                            <SelectContent>
                                {timeRanges.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.district ?? 'all'}
                            onValueChange={(value) => {
                                if (value === 'all') {
                                    void handleFilterChange({ district: undefined });
                                    return;
                                }
                                void handleFilterChange({ district: value });
                            }}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="All districts" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All districts</SelectItem>
                                {districts.map((district) => (
                                    <SelectItem key={district} value={district}>
                                        {district}
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
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>

                        <Button
                            type="button"
                            onClick={() => {
                                void generateReport();
                            }}
                            disabled={reportLoading}
                        >
                            <Sparkles className={`h-4 w-4 ${reportLoading ? 'animate-pulse' : ''}`} />
                            Generate Report
                        </Button>
                    </div>
                </div>

                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load analysis</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <section>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {(loading || !data)
                            ? summaryCards.map((card) => (
                                  <div key={card.label} className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                                      <Skeleton className="h-4 w-24" />
                                      <Skeleton className="mt-4 h-8 w-32" />
                                      <Skeleton className="mt-2 h-3 w-20" />
                                  </div>
                              ))
                            : summaryCards.map((card) => {
                                  const Icon = card.icon;
                                  return (
                                      <div key={card.label} className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                          <div className="flex items-center justify-between">
                                              <div>
                                                  <p className="text-sm text-gray-500 dark:text-gray-400">{card.label}</p>
                                                  <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{card.value}</p>
                                              </div>
                                              <div className="rounded-full bg-blue-50 p-2 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                                                  <Icon className="h-5 w-5" />
                                              </div>
                                          </div>
                                          {card.change !== undefined && card.change !== null && (
                                              <p className={`mt-3 text-xs font-medium ${(card.change ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                                  {card.change >= 0 ? '+' : ''}{card.change.toFixed(2)}% vs previous period
                                              </p>
                                          )}
                                      </div>
                                  );
                              })}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <Skeleton className="h-72 w-full" />
                        ) : (
                            <TrendChart data={data.trends.timeseries} />
                        )}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? (
                            <Skeleton className="h-72 w-full" />
                        ) : (
                            <div className="flex h-full flex-col justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Key Market Stats</h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Snapshot of current market health.
                                    </p>
                                </div>
                                <dl className="mt-6 space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                    <div className="flex items-center justify-between">
                                        <dt>Generated</dt>
                                        <dd>{meta?.generated_at ? new Date(meta.generated_at).toLocaleString() : 'N/A'}</dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>Listings analysed</dt>
                                        <dd>{typeof meta?.property_count === 'number' ? meta.property_count.toLocaleString() : 'N/A'}</dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>Signals</dt>
                                        <dd>
                                            {(investment?.signals.bullish.length ?? 0)} bullish · {(investment?.signals.bearish.length ?? 0)} bearish
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>Hotspots tracked</dt>
                                        <dd>{investment?.hotspots.length ?? 0}</dd>
                                    </div>
                                </dl>
                            </div>
                        )}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? <Skeleton className="h-72 w-full" /> : <PriceComparisonChart data={data.price_comparison.districts} />}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        {loading || !data ? <Skeleton className="h-72 w-full" /> : <PriceDistributionChart data={data.price_comparison.distribution} />}
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Investment Hotspots</h3>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Weighted score combining growth, volume, and affordability.
                                </p>
                            </div>
                        </div>
                        {loading || !data ? <Skeleton className="h-64 w-full" /> : <HotspotList data={data.investment.hotspots} />}
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Multi-dimensional Insights</h3>
                        {loading || !data ? (
                            <div className="mt-4 space-y-3">
                                <Skeleton className="h-12 w-full" />
                                <Skeleton className="h-12 w-3/4" />
                                <Skeleton className="h-12 w-5/6" />
                            </div>
                        ) : (
                            <div className="mt-4 space-y-5 text-sm text-gray-700 dark:text-gray-200">
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Temporal</h4>
                                    <p className="mt-1">
                                        {data.multi_dimensional.temporal.slice(-3).map((item) => `${item.period}: $${item.average_rent.toLocaleString()}`).join(' · ')}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Spatial leaders</h4>
                                    <p className="mt-1">
                                        {data.multi_dimensional.spatial
                                            .slice(0, 3)
                                            .map((item) => `${item.district} (${item.listings} listings)`)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Segment highlight</h4>
                                    <p className="mt-1">
                                        {data.multi_dimensional.price_segments.by_room_type
                                            .slice(0, 2)
                                            .map((item) => `${item.pattern ?? 'Unknown'}: ${item.average_rent ? `$${item.average_rent.toLocaleString()}` : 'N/A'}`)
                                            .join(' · ')}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Automated Market Report</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Generate an executive summary with recommendations and shareable insights.
                    </p>
                    <div className="mt-6">
                        <ReportSummary report={report} isLoading={reportLoading} error={reportError} />
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

