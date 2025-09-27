import type {
    MarketAnalysisFilters,
    MarketAnalysisOverview,
    MarketAnalysisReport,
} from '@/types/analysis';
import { useCallback, useEffect, useMemo, useState } from 'react';

interface UseMarketAnalysisOptions {
    initialFilters?: MarketAnalysisFilters;
    autoLoad?: boolean;
}

interface UseMarketAnalysis {
    data: MarketAnalysisOverview | null;
    loading: boolean;
    error: string | null;
    filters: MarketAnalysisFilters;
    refresh: (overrideFilters?: MarketAnalysisFilters) => Promise<void>;
    setFilters: (next: MarketAnalysisFilters) => void;
    report: MarketAnalysisReport | null;
    reportLoading: boolean;
    reportError: string | null;
    generateReport: (overrideFilters?: MarketAnalysisFilters) => Promise<void>;
}

export function useMarketAnalysis(
    options: UseMarketAnalysisOptions = {},
): UseMarketAnalysis {
    const { initialFilters = { time_range: '12m' }, autoLoad = true } = options;

    const [data, setData] = useState<MarketAnalysisOverview | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [filters, setFiltersState] =
        useState<MarketAnalysisFilters>(initialFilters);

    const [report, setReport] = useState<MarketAnalysisReport | null>(null);
    const [reportLoading, setReportLoading] = useState<boolean>(false);
    const [reportError, setReportError] = useState<string | null>(null);

    const buildQuery = useCallback((payload: MarketAnalysisFilters) => {
        const params = new URLSearchParams();
        Object.entries(payload)
            .filter(
                ([, value]) =>
                    value !== undefined && value !== null && value !== '',
            )
            .forEach(([key, value]) => {
                params.set(key, String(value));
            });

        return params.toString();
    }, []);

    const mergedFilters = useMemo(
        () => ({ ...initialFilters, ...filters }),
        [initialFilters, filters],
    );

    const fetchOverview = useCallback(
        async (overrideFilters?: MarketAnalysisFilters) => {
            const nextFilters = { ...mergedFilters, ...overrideFilters };
            setLoading(true);
            setError(null);

            try {
                const queryString = buildQuery(nextFilters);
                const response = await fetch(
                    `/api/analysis/overview${queryString ? `?${queryString}` : ''}`,
                    {
                        headers: {
                            Accept: 'application/json',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to load market analysis overview.');
                }

                const body = await response.json();
                setData(body.data as MarketAnalysisOverview);
                setFiltersState(nextFilters);
            } catch (exception) {
                setError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unexpected error.',
                );
            } finally {
                setLoading(false);
            }
        },
        [buildQuery, mergedFilters],
    );

    const generateReport = useCallback(
        async (overrideFilters?: MarketAnalysisFilters) => {
            const payload = { ...mergedFilters, ...overrideFilters };
            setReportLoading(true);
            setReportError(null);

            try {
                const response = await fetch('/api/analysis/report', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Failed to generate market report.');
                }

                const body = await response.json();
                setReport(body.report as MarketAnalysisReport);
            } catch (exception) {
                setReportError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unexpected error.',
                );
            } finally {
                setReportLoading(false);
            }
        },
        [mergedFilters],
    );

    useEffect(() => {
        if (autoLoad) {
            void fetchOverview();
        }
    }, [autoLoad, fetchOverview]);

    const setFilters = useCallback((next: MarketAnalysisFilters) => {
        setFiltersState((previous) => ({ ...previous, ...next }));
    }, []);

    return {
        data,
        loading,
        error,
        filters: mergedFilters,
        refresh: fetchOverview,
        setFilters,
        report,
        reportLoading,
        reportError,
        generateReport,
    };
}
