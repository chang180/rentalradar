/**
 * 效能工具函數
 */

export interface PerformanceMetrics {
    timestamp: number;
    responseTime: number;
    memoryUsage: number;
    queryCount: number;
    cacheHitRate: number;
    activeConnections: number;
    cpuUsage?: number;
    networkLatency?: number;
}

export interface PerformanceThresholds {
    responseTime: { good: number; warning: number };
    memoryUsage: { good: number; warning: number };
    cacheHitRate: { good: number; warning: number };
}

export class PerformanceUtils {
    private static metrics: PerformanceMetrics[] = [];
    private static maxMetrics = 100;

    /**
     * 記錄效能指標
     */
    static recordMetrics(metrics: PerformanceMetrics): void {
        this.metrics.push(metrics);

        // 保持指標數量在限制內
        if (this.metrics.length > this.maxMetrics) {
            this.metrics = this.metrics.slice(-this.maxMetrics);
        }
    }

    /**
     * 獲取效能指標
     */
    static getMetrics(): PerformanceMetrics[] {
        return [...this.metrics];
    }

    /**
     * 清除效能指標
     */
    static clearMetrics(): void {
        this.metrics = [];
    }

    /**
     * 獲取平均效能指標
     */
    static getAverageMetrics(timeWindow: number = 300000): PerformanceMetrics | null {
        const now = Date.now();
        const recentMetrics = this.metrics.filter(
            metric => now - metric.timestamp <= timeWindow
        );

        if (recentMetrics.length === 0) {
            return null;
        }

        const sum = recentMetrics.reduce(
            (acc, metric) => ({
                timestamp: now,
                responseTime: acc.responseTime + metric.responseTime,
                memoryUsage: acc.memoryUsage + metric.memoryUsage,
                queryCount: acc.queryCount + metric.queryCount,
                cacheHitRate: acc.cacheHitRate + metric.cacheHitRate,
                activeConnections: acc.activeConnections + metric.activeConnections,
                cpuUsage: (acc.cpuUsage || 0) + (metric.cpuUsage || 0),
                networkLatency: (acc.networkLatency || 0) + (metric.networkLatency || 0),
            }),
            {
                timestamp: 0,
                responseTime: 0,
                memoryUsage: 0,
                queryCount: 0,
                cacheHitRate: 0,
                activeConnections: 0,
                cpuUsage: 0,
                networkLatency: 0,
            }
        );

        const count = recentMetrics.length;
        return {
            timestamp: now,
            responseTime: sum.responseTime / count,
            memoryUsage: sum.memoryUsage / count,
            queryCount: sum.queryCount / count,
            cacheHitRate: sum.cacheHitRate / count,
            activeConnections: sum.activeConnections / count,
            cpuUsage: sum.cpuUsage / count,
            networkLatency: sum.networkLatency / count,
        };
    }

    /**
     * 檢查效能健康狀態
     */
    static checkHealth(thresholds: PerformanceThresholds = {
        responseTime: { good: 100, warning: 500 },
        memoryUsage: { good: 50, warning: 100 },
        cacheHitRate: { good: 80, warning: 60 },
    }): {
        status: 'good' | 'warning' | 'critical';
        issues: string[];
        score: number;
    } {
        const average = this.getAverageMetrics();
        if (!average) {
            return {
                status: 'good',
                issues: [],
                score: 100,
            };
        }

        const issues: string[] = [];
        let score = 100;

        // 檢查響應時間
        if (average.responseTime > thresholds.responseTime.warning) {
            issues.push(`響應時間過慢: ${average.responseTime.toFixed(2)}ms`);
            score -= 30;
        } else if (average.responseTime > thresholds.responseTime.good) {
            issues.push(`響應時間較慢: ${average.responseTime.toFixed(2)}ms`);
            score -= 15;
        }

        // 檢查記憶體使用
        if (average.memoryUsage > thresholds.memoryUsage.warning) {
            issues.push(`記憶體使用過高: ${average.memoryUsage.toFixed(2)}MB`);
            score -= 25;
        } else if (average.memoryUsage > thresholds.memoryUsage.good) {
            issues.push(`記憶體使用較高: ${average.memoryUsage.toFixed(2)}MB`);
            score -= 10;
        }

        // 檢查快取命中率
        if (average.cacheHitRate < thresholds.cacheHitRate.warning) {
            issues.push(`快取命中率過低: ${average.cacheHitRate.toFixed(1)}%`);
            score -= 20;
        } else if (average.cacheHitRate < thresholds.cacheHitRate.good) {
            issues.push(`快取命中率較低: ${average.cacheHitRate.toFixed(1)}%`);
            score -= 10;
        }

        const status = score >= 80 ? 'good' : score >= 60 ? 'warning' : 'critical';

        return {
            status,
            issues,
            score: Math.max(0, score),
        };
    }

    /**
     * 格式化效能指標
     */
    static formatMetrics(metrics: PerformanceMetrics): {
        responseTime: string;
        memoryUsage: string;
        queryCount: string;
        cacheHitRate: string;
        activeConnections: string;
    } {
        return {
            responseTime: `${metrics.responseTime.toFixed(2)}ms`,
            memoryUsage: `${metrics.memoryUsage.toFixed(2)}MB`,
            queryCount: metrics.queryCount.toString(),
            cacheHitRate: `${metrics.cacheHitRate.toFixed(1)}%`,
            activeConnections: metrics.activeConnections.toString(),
        };
    }

    /**
     * 獲取效能趨勢
     */
    static getPerformanceTrend(timeWindow: number = 600000): {
        responseTime: 'improving' | 'stable' | 'degrading';
        memoryUsage: 'improving' | 'stable' | 'degrading';
        overall: 'improving' | 'stable' | 'degrading';
    } {
        const now = Date.now();
        const recentMetrics = this.metrics.filter(
            metric => now - metric.timestamp <= timeWindow
        );

        if (recentMetrics.length < 2) {
            return {
                responseTime: 'stable',
                memoryUsage: 'stable',
                overall: 'stable',
            };
        }

        const sortedMetrics = recentMetrics.sort((a, b) => a.timestamp - b.timestamp);
        const firstHalf = sortedMetrics.slice(0, Math.floor(sortedMetrics.length / 2));
        const secondHalf = sortedMetrics.slice(Math.floor(sortedMetrics.length / 2));

        const getTrend = (first: number[], second: number[]) => {
            const firstAvg = first.reduce((a, b) => a + b, 0) / first.length;
            const secondAvg = second.reduce((a, b) => a + b, 0) / second.length;
            const change = ((secondAvg - firstAvg) / firstAvg) * 100;

            if (change > 10) return 'degrading';
            if (change < -10) return 'improving';
            return 'stable';
        };

        const responseTimeTrend = getTrend(
            firstHalf.map(m => m.responseTime),
            secondHalf.map(m => m.responseTime)
        );

        const memoryTrend = getTrend(
            firstHalf.map(m => m.memoryUsage),
            secondHalf.map(m => m.memoryUsage)
        );

        const overall = responseTimeTrend === 'degrading' || memoryTrend === 'degrading'
            ? 'degrading'
            : responseTimeTrend === 'improving' || memoryTrend === 'improving'
            ? 'improving'
            : 'stable';

        return {
            responseTime: responseTimeTrend,
            memoryUsage: memoryTrend,
            overall,
        };
    }

    /**
     * 生成效能報告
     */
    static generateReport(): {
        summary: string;
        metrics: PerformanceMetrics | null;
        health: ReturnType<typeof this.checkHealth>;
        trend: ReturnType<typeof this.getPerformanceTrend>;
        recommendations: string[];
    } {
        const metrics = this.getAverageMetrics();
        const health = this.checkHealth();
        const trend = this.getPerformanceTrend();

        const recommendations: string[] = [];

        if (health.issues.length > 0) {
            if (health.issues.some(issue => issue.includes('響應時間'))) {
                recommendations.push('考慮優化資料庫查詢或增加快取');
            }
            if (health.issues.some(issue => issue.includes('記憶體'))) {
                recommendations.push('檢查記憶體洩漏或優化資料結構');
            }
            if (health.issues.some(issue => issue.includes('快取'))) {
                recommendations.push('調整快取策略或增加快取容量');
            }
        }

        if (trend.overall === 'degrading') {
            recommendations.push('監控系統負載，考慮擴展資源');
        }

        const summary = `效能狀態: ${health.status} (${health.score}/100)`;

        return {
            summary,
            metrics,
            health,
            trend,
            recommendations,
        };
    }
}
