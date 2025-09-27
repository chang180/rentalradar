/**
 * 熱力圖分析演算法 (JavaScript 實現)
 * 適用於 Hostinger 共享空間
 */

export class HeatmapAlgorithm {
    constructor() {
        this.colorScale = {
            min: 0.1,
            max: 1.0,
            colors: ['#00ff00', '#ffff00', '#ff0000']
        };
    }

    /**
     * 生成熱力圖資料
     */
    generateHeatmap(data, resolution = 'medium', options = {}) {
        const gridSizes = { low: 20, medium: 50, high: 100 };
        const gridSize = gridSizes[resolution] || 50;

        // 計算邊界
        const bounds = this.calculateBounds(data);

        // 建立網格
        const latStep = (bounds.north - bounds.south) / gridSize;
        const lngStep = (bounds.east - bounds.west) / gridSize;

        const grid = {};
        const allPrices = [];

        data.forEach(item => {
            const lat = item.lat;
            const lng = item.lng;
            const price = item.price || item.rent_per_month || 0;

            if (price > 0) allPrices.push(price);

            // 計算網格索引
            const latIndex = Math.floor((lat - bounds.south) / latStep);
            const lngIndex = Math.floor((lng - bounds.west) / lngStep);
            const key = `${latIndex}_${lngIndex}`;

            if (!grid[key]) {
                grid[key] = {
                    lat: bounds.south + (latIndex + 0.5) * latStep,
                    lng: bounds.west + (lngIndex + 0.5) * lngStep,
                    totalPrice: 0,
                    count: 0,
                    maxPrice: 0,
                    minPrice: Number.MAX_SAFE_INTEGER,
                    prices: []
                };
            }

            grid[key].totalPrice += price;
            grid[key].count++;
            grid[key].maxPrice = Math.max(grid[key].maxPrice, price);
            grid[key].minPrice = Math.min(grid[key].minPrice, price);
            grid[key].prices.push(price);
        });

        // 計算全域價格統計用於更準確的標準化
        const globalPriceStats = this.calculateGlobalPriceStats(allPrices);

        // 轉換為熱力圖點
        const heatmapPoints = Object.values(grid)
            .filter(cell => cell.count > 0)
            .map(cell => {
                const avgPrice = cell.totalPrice / cell.count;
                const weight = this.normalizeWeightAdvanced(avgPrice, globalPriceStats);
                const intensity = this.calculateIntensity(cell.count, cell.prices, options);

                return {
                    lat: cell.lat,
                    lng: cell.lng,
                    weight: weight,
                    intensity: intensity,
                    price_range: this.getPriceRange(avgPrice),
                    count: cell.count,
                    avg_price: Math.round(avgPrice),
                    max_price: cell.maxPrice,
                    min_price: cell.minPrice === Number.MAX_SAFE_INTEGER ? 0 : cell.minPrice,
                    color: this.getAdvancedColor(weight, intensity),
                    radius: this.calculateRadius(cell.count, intensity),
                };
            });

        return {
            success: true,
            heatmap_points: heatmapPoints,
            color_scale: this.colorScale,
            global_stats: globalPriceStats,
            statistics: {
                total_points: heatmapPoints.length,
                density_range: {
                    min: Math.min(...heatmapPoints.map(p => p.weight)),
                    max: Math.max(...heatmapPoints.map(p => p.weight))
                },
                intensity_range: {
                    min: Math.min(...heatmapPoints.map(p => p.intensity)),
                    max: Math.max(...heatmapPoints.map(p => p.intensity))
                }
            }
        };
    }

    /**
     * 計算全域價格統計
     */
    calculateGlobalPriceStats(prices) {
        if (prices.length === 0) {
            return { min: 0, max: 100000, avg: 25000, median: 25000, p25: 15000, p75: 35000 };
        }

        const sorted = [...prices].sort((a, b) => a - b);
        const min = sorted[0];
        const max = sorted[sorted.length - 1];
        const avg = prices.reduce((sum, price) => sum + price, 0) / prices.length;
        const median = this.median(sorted);
        const p25 = this.percentile(sorted, 25);
        const p75 = this.percentile(sorted, 75);

        return { min, max, avg, median, p25, p75 };
    }

    /**
     * 計算百分位數
     */
    percentile(sortedArray, percentile) {
        const index = (percentile / 100) * (sortedArray.length - 1);
        const lower = Math.floor(index);
        const upper = Math.ceil(index);
        const weight = index % 1;

        if (upper >= sortedArray.length) return sortedArray[sortedArray.length - 1];
        if (lower === upper) return sortedArray[lower];

        return sortedArray[lower] * (1 - weight) + sortedArray[upper] * weight;
    }

    /**
     * 進階權重標準化
     */
    normalizeWeightAdvanced(price, stats) {
        // 使用四分位距進行更準確的標準化
        const iqr = stats.p75 - stats.p25;
        const normalizedPrice = Math.max(0, Math.min(1, (price - stats.p25) / iqr));

        // 應用非線性轉換突出差異
        return Math.pow(normalizedPrice, 0.7);
    }

    /**
     * 計算強度（基於密度和價格變異）
     */
    calculateIntensity(count, prices, options = {}) {
        const minIntensity = options.minIntensity || 0.1;
        const maxIntensity = options.maxIntensity || 1.0;

        // 基於數量的強度
        const countIntensity = Math.min(1, count / 20);

        // 基於價格變異的強度
        let priceVariationIntensity = 0.5;
        if (prices.length > 1) {
            const avg = prices.reduce((sum, p) => sum + p, 0) / prices.length;
            const variance = prices.reduce((sum, p) => sum + Math.pow(p - avg, 2), 0) / prices.length;
            const cv = Math.sqrt(variance) / avg; // 變異係數
            priceVariationIntensity = Math.min(1, cv / 0.5); // 標準化變異係數
        }

        const intensity = (countIntensity + priceVariationIntensity) / 2;
        return Math.max(minIntensity, Math.min(maxIntensity, intensity));
    }

    /**
     * 取得進階顏色
     */
    getAdvancedColor(weight, intensity) {
        // 基於權重選擇基礎顏色
        let baseColor;
        if (weight < 0.3) {
            baseColor = { r: 0, g: 255, b: 0 }; // 綠色
        } else if (weight < 0.7) {
            baseColor = { r: 255, g: 255, b: 0 }; // 黃色
        } else {
            baseColor = { r: 255, g: 0, b: 0 }; // 紅色
        }

        // 根據強度調整透明度
        const alpha = Math.max(0.2, intensity);

        return `rgba(${baseColor.r}, ${baseColor.g}, ${baseColor.b}, ${alpha.toFixed(2)})`;
    }

    /**
     * 計算半徑
     */
    calculateRadius(count, intensity) {
        const baseRadius = 5;
        const countFactor = Math.min(3, count / 10); // 最多3倍
        const intensityFactor = 1 + intensity; // 1-2倍

        return Math.round(baseRadius * countFactor * intensityFactor);
    }

    /**
     * 密度分析
     */
    densityAnalysis(data, radius = 0.01) {
        const densityPoints = [];
        
        data.forEach(item => {
            const lat = item.lat;
            const lng = item.lng;
            const price = item.price || 0;
            
            // 計算周圍點的密度
            let density = 0;
            let nearbyCount = 0;
            
            data.forEach(otherItem => {
                if (otherItem !== item) {
                    const distance = this.calculateDistance(
                        [lat, lng],
                        [otherItem.lat, otherItem.lng]
                    );
                    
                    if (distance <= radius) {
                        density += otherItem.price || 0;
                        nearbyCount++;
                    }
                }
            });
            
            const avgDensity = nearbyCount > 0 ? density / nearbyCount : 0;
            const weight = this.normalizeWeight(avgDensity);
            
            densityPoints.push({
                lat: lat,
                lng: lng,
                weight: weight,
                density: avgDensity,
                nearby_count: nearbyCount,
                price_range: this.getPriceRange(price)
            });
        });

        return {
            success: true,
            density_points: densityPoints,
            color_scale: this.colorScale,
            statistics: {
                total_points: densityPoints.length,
                average_density: densityPoints.reduce((sum, p) => sum + p.density, 0) / densityPoints.length
            }
        };
    }

    /**
     * 計算邊界
     */
    calculateBounds(data) {
        if (data.length === 0) {
            return { north: 0, south: 0, east: 0, west: 0 };
        }

        const lats = data.map(item => item.lat);
        const lngs = data.map(item => item.lng);

        return {
            north: Math.max(...lats),
            south: Math.min(...lats),
            east: Math.max(...lngs),
            west: Math.min(...lngs)
        };
    }

    /**
     * 標準化權重
     */
    normalizeWeight(price) {
        // 將價格標準化到 0.1-1.0 範圍
        const minPrice = 10000;
        const maxPrice = 100000;
        const normalized = Math.max(0.1, Math.min(1.0, (price - minPrice) / (maxPrice - minPrice)));
        return Math.round(normalized * 100) / 100;
    }

    /**
     * 取得價格區間標籤
     */
    getPriceRange(price) {
        if (price < 20000) return '20000以下';
        if (price < 30000) return '20000-30000';
        if (price < 40000) return '30000-40000';
        return '40000以上';
    }

    /**
     * 計算兩點間距離
     */
    calculateDistance(point1, point2) {
        const [lat1, lng1] = point1;
        const [lat2, lng2] = point2;

        const earthRadius = 6371; // 地球半徑 (km)
        const dLat = this.toRadians(lat2 - lat1);
        const dLng = this.toRadians(lng2 - lng1);
        
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + 
                  Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) * 
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        
        return earthRadius * c;
    }

    /**
     * 角度轉弧度
     */
    toRadians(degrees) {
        return degrees * (Math.PI / 180);
    }

    /**
     * 取得顏色
     */
    getColor(weight) {
        const { min, max, colors } = this.colorScale;
        const normalizedWeight = (weight - min) / (max - min);
        
        if (normalizedWeight <= 0) return colors[0];
        if (normalizedWeight >= 1) return colors[colors.length - 1];
        
        const colorIndex = normalizedWeight * (colors.length - 1);
        const lowerIndex = Math.floor(colorIndex);
        const upperIndex = Math.ceil(colorIndex);
        const ratio = colorIndex - lowerIndex;
        
        return this.interpolateColor(colors[lowerIndex], colors[upperIndex], ratio);
    }

    /**
     * 顏色插值
     */
    interpolateColor(color1, color2, ratio) {
        const hex1 = color1.replace('#', '');
        const hex2 = color2.replace('#', '');
        
        const r1 = parseInt(hex1.substr(0, 2), 16);
        const g1 = parseInt(hex1.substr(2, 2), 16);
        const b1 = parseInt(hex1.substr(4, 2), 16);
        
        const r2 = parseInt(hex2.substr(0, 2), 16);
        const g2 = parseInt(hex2.substr(2, 2), 16);
        const b2 = parseInt(hex2.substr(4, 2), 16);
        
        const r = Math.round(r1 + (r2 - r1) * ratio);
        const g = Math.round(g1 + (g2 - g1) * ratio);
        const b = Math.round(b1 + (b2 - b1) * ratio);
        
        return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
    }
}
