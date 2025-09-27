import type { InvestmentHotspot } from '@/types/analysis';

interface HotspotListProps {
    data: InvestmentHotspot[];
}

export function HotspotList({ data }: HotspotListProps) {
    if (!data || data.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-gray-200 p-6 text-center dark:border-gray-700">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    No investment hotspots detected for the selected filters.
                    Try widening the time range.
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                District
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                Score
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                Trend
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                Avg Rent
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                Price / Ping
                            </th>
                            <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-300">
                                Listings
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        {data.map((hotspot) => (
                            <tr
                                key={hotspot.district}
                                className="hover:bg-gray-50/80 dark:hover:bg-gray-800/60"
                            >
                                <td className="px-4 py-3 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                    {hotspot.district}
                                </td>
                                <td className="px-4 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                    {hotspot.score.toFixed(2)}
                                </td>
                                <td className="px-4 py-3 text-sm whitespace-nowrap text-gray-700 capitalize dark:text-gray-200">
                                    {hotspot.trend_direction}
                                </td>
                                <td className="px-4 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                    ${hotspot.average_rent.toLocaleString()}
                                </td>
                                <td className="px-4 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                    {hotspot.price_per_ping
                                        ? `$${hotspot.price_per_ping.toLocaleString()}`
                                        : 'N/A'}
                                </td>
                                <td className="px-4 py-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-200">
                                    {hotspot.listings}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default HotspotList;
