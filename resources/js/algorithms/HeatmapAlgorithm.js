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
    generateHeatmap(data, resolution = 'medium') {
        const gridSizes = { low: 20, medium: 50, high: 100 };
        const gridSize = gridSizes[resolution] || 50;

        // 計算邊界
        const bounds = this.calculateBounds(data);
        
        // 建立網格
        const latStep = (bounds.north - bounds.south) / gridSize;
        const lngStep = (bounds.east - bounds.west) / gridSize;

        const grid = {};
        
        data.forEach(item => {
            const lat = item.lat;
            const lng = item.lng;
            const price = item.price || 0;
            
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
                    maxPrice: 0
                };
            }
            
            grid[key].totalPrice += price;
            grid[key].count++;
            grid[key].maxPrice = Math.max(grid[key].maxPrice, price);
        });

        // 轉換為熱力圖點
        const heatmapPoints = Object.values(grid).map(cell => {
            const avgPrice = cell.totalPrice / cell.count;
            const weight = this.normalizeWeight(avgPrice);
            
            return {
                lat: cell.lat,
                lng: cell.lng,
                weight: weight,
                price_range: this.getPriceRange(avgPrice),
                count: cell.count,
                max_price: cell.maxPrice
            };
        });

        return {
            success: true,
            heatmap_points: heatmapPoints,
            color_scale: this.colorScale,
            statistics: {
                total_points: heatmapPoints.length,
                density_range: {
                    min: Math.min(...heatmapPoints.map(p => p.weight)),
                    max: Math.max(...heatmapPoints.map(p => p.weight))
                }
            }
        };
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
