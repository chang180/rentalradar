export interface MarketTrendPoint {
    period: string;
    average_rent: number;
    median_rent: number;
    volume: number;
    moving_average?: number | null;
    average_area?: number | null;
    price_per_ping?: number | null;
}

export interface MarketTrendSummary {
    current_average: number | null;
    current_volume: number | null;
    month_over_month_change: number | null;
    year_over_year_change: number | null;
    volume_trend: number | null;
}

export interface MarketForecast {
    method: string;
    values: number[];
    confidence: number;
}

export interface PriceDistributionSegment {
    label: string;
    min: number;
    max: number | null;
    count: number;
}

export interface PriceDistribution {
    segments: PriceDistributionSegment[];
    median: number | null;
}

export interface PriceComparisonDistrict {
    district: string;
    average_rent: number;
    median_rent: number;
    price_range: {
        min: number | null;
        max: number | null;
        p25: number | null;
        p75: number | null;
    };
    average_area: number | null;
    price_per_ping: number | null;
    listings: number;
    trend_change: number | null;
    trend_direction: 'up' | 'down' | 'neutral';
}

export interface PriceComparisonData {
    districts: PriceComparisonDistrict[];
    summary: {
        top_districts: PriceComparisonDistrict[];
        most_affordable: PriceComparisonDistrict[];
    };
    distribution: PriceDistribution;
    filters: Record<string, unknown>;
}

export interface InvestmentSignals {
    bullish: string[];
    bearish: string[];
    neutral: string[];
}

export interface InvestmentHotspot {
    district: string;
    score: number;
    trend_direction: 'up' | 'down' | 'neutral';
    average_rent: number;
    price_per_ping: number | null;
    listings: number;
}

export interface InvestmentInsights {
    hotspots: InvestmentHotspot[];
    signals: InvestmentSignals;
    confidence: number;
}

export interface MultiDimensionalAnalysis {
    temporal: Array<{
        period: string;
        average_rent: number;
        median_rent: number;
        volume: number;
    }>;
    spatial: Array<{
        district: string;
        listings: number;
        average_rent: number;
        median_rent: number;
        price_per_ping: number | null;
    }>;
    price_segments: {
        by_room_type: Array<{
            pattern: string | null;
            average_rent: number | null;
            listings: number;
        }>;
        by_building_type: Array<{
            building_type: string;
            average_rent: number | null;
            listings: number;
        }>;
        price_distribution: PriceDistribution;
    };
}

export interface InteractiveDatasets {
    trend_series: MarketTrendPoint[];
    price_matrix: PriceComparisonDistrict[];
    heatmap: MultiDimensionalAnalysis['spatial'];
}

export interface MarketAnalysisOverview {
    trends: {
        timeseries: MarketTrendPoint[];
        summary: MarketTrendSummary;
        forecast: MarketForecast;
    };
    price_comparison: PriceComparisonData;
    investment: InvestmentInsights;
    multi_dimensional: MultiDimensionalAnalysis;
    interactive: InteractiveDatasets;
    meta: {
        generated_at: string;
        time_range: string;
        filters: Record<string, unknown>;
        property_count: number;
    };
}

export interface MarketReportSection {
    title: string;
    content: string;
    metrics: Record<string, unknown>;
}

export interface MarketAnalysisReport {
    generated_at: string;
    time_range: string;
    filters: Record<string, unknown>;
    summary: string;
    highlights: {
        pricing: string;
        top_market: string;
        hotspot: string;
    };
    recommendations: string[];
    sections: MarketReportSection[];
}

export interface MarketAnalysisFilters {
    time_range?: string;
    district?: string;
    building_type?: string;
}
