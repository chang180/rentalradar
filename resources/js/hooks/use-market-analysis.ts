import type {
    MarketAnalysisFilters,
    MarketAnalysisOverview,
    MarketAnalysisReport,
} from '@/types/analysis';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

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

const DEFAULT_TIME_RANGE = '12m';

export function useMarketAnalysis(
    options: UseMarketAnalysisOptions = {},
): UseMarketAnalysis {
    const { initialFilters: providedInitialFilters, autoLoad = true } = options;

    const defaultFiltersRef = useRef<MarketAnalysisFilters>({
        time_range: providedInitialFilters?.time_range ?? DEFAULT_TIME_RANGE,
        ...providedInitialFilters,
    });

    const [data, setData] = useState<MarketAnalysisOverview | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [filters, setFiltersState] = useState<MarketAnalysisFilters>(
        defaultFiltersRef.current,
    );

    const filtersRef = useRef<MarketAnalysisFilters>(defaultFiltersRef.current);

    useEffect(() => {
        filtersRef.current = filters;
    }, [filters]);

    useEffect(() => {
        if (!providedInitialFilters) {
            return;
        }

        const nextDefaults: MarketAnalysisFilters = {
            time_range: providedInitialFilters.time_range ?? DEFAULT_TIME_RANGE,
            ...providedInitialFilters,
        };

        defaultFiltersRef.current = nextDefaults;
        filtersRef.current = nextDefaults;
        setFiltersState(nextDefaults);
    }, [providedInitialFilters]);

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

    const resolveFilters = useCallback(
        (overrideFilters?: MarketAnalysisFilters) => ({
            ...defaultFiltersRef.current,
            ...filtersRef.current,
            ...overrideFilters,
        }),
        [defaultFiltersRef, filtersRef],
    );

    const fetchOverview = useCallback(
        async (overrideFilters?: MarketAnalysisFilters) => {
            const nextFilters = resolveFilters(overrideFilters);

            filtersRef.current = nextFilters;
            setFiltersState(nextFilters);
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
        [buildQuery, resolveFilters],
    );

    const generateReport = useCallback(
        async (overrideFilters?: MarketAnalysisFilters) => {
            const payload = resolveFilters(overrideFilters);
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
        [resolveFilters],
    );

    useEffect(() => {
        if (autoLoad) {
            void fetchOverview();
        }
    }, [autoLoad, fetchOverview]);

    const setFilters = useCallback((next: MarketAnalysisFilters) => {
        setFiltersState((previous) => {
            const updated = { ...previous, ...next };
            filtersRef.current = updated;

            return updated;
        });
    }, [filtersRef]);

    return {
        data,
        loading,
        error,
        filters,
        refresh: fetchOverview,
        setFilters,
        report,
        reportLoading,
        reportError,
        generateReport,
    };
}

