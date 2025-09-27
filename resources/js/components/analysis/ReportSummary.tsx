import React from 'react';
import type { MarketAnalysisReport } from '@/types/analysis';

interface ReportSummaryProps {
    report: MarketAnalysisReport | null;
    isLoading: boolean;
    error: string | null;
}

export function ReportSummary({ report, isLoading, error }: ReportSummaryProps) {
    if (isLoading) {
        return (
            <div className="rounded-lg border border-gray-200 p-6 dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">Generating report...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-lg border border-red-200 bg-red-50 p-6 text-red-700 dark:border-red-500/40 dark:bg-red-500/10">
                <p className="text-sm">{error}</p>
            </div>
        );
    }

    if (!report) {
        return (
            <div className="rounded-lg border border-dashed border-gray-200 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Generate a report to receive executive-ready insights.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div className="flex flex-col gap-2">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Executive Summary</h3>
                    <p className="text-sm text-gray-600 dark:text-gray-300">{report.summary}</p>
                </div>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-md border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pricing</p>
                        <p className="mt-1 text-sm text-gray-700 dark:text-gray-200">{report.highlights.pricing}</p>
                    </div>
                    <div className="rounded-md border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Top Market</p>
                        <p className="mt-1 text-sm text-gray-700 dark:text-gray-200">{report.highlights.top_market}</p>
                    </div>
                    <div className="rounded-md border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hotspot</p>
                        <p className="mt-1 text-sm text-gray-700 dark:text-gray-200">{report.highlights.hotspot}</p>
                    </div>
                </div>
            </div>

            {report.recommendations.length > 0 && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-500/40 dark:bg-blue-500/10">
                    <h4 className="text-sm font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">Recommendations</h4>
                    <ul className="mt-3 list-disc space-y-2 pl-5 text-sm text-blue-900 dark:text-blue-100">
                        {report.recommendations.map((item, index) => (
                            <li key={index}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                {report.sections.map((section) => (
                    <div key={section.title} className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <h4 className="text-sm font-semibold text-gray-900 dark:text-white">{section.title}</h4>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">{section.content}</p>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default ReportSummary;
