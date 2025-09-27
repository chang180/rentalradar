interface MapPoint {
    lat: number;
    lng: number;
    price?: number;
    weight?: number;
}

interface Cluster {
    id: string;
    center: { lat: number; lng: number };
    count: number;
    bounds: {
        north: number;
        south: number;
        east: number;
        west: number;
    };
}

interface HeatmapPoint {
    lat: number;
    lng: number;
    weight: number;
    price_range?: string;
}

export class AIMapService {
    /**
     * 客戶端聚合演算法 - K-means 實現
     */
    static clusterPoints(points: MapPoint[], k: number = 10): Cluster[] {
        if (points.length <= k) {
            return points.map((point, index) => ({
                id: `cluster_${index}`,
                center: { lat: point.lat, lng: point.lng },
                count: 1,
                bounds: {
                    north: point.lat,
                    south: point.lat,
                    east: point.lng,
                    west: point.lng,
                },
            }));
        }

        // 初始化中心點
        const centers: { lat: number; lng: number }[] = [];
        const indices = this.getRandomIndices(points.length, k);
        indices.forEach(index => {
            centers.push({ lat: points[index].lat, lng: points[index].lng });
        });

        const maxIterations = 10;
        let clusters: Cluster[] = [];

        for (let iter = 0; iter < maxIterations; iter++) {
            const assignments: number[] = [];
            const newCenters: { lat: number; lng: number }[] = [];
            const counts: number[] = new Array(k).fill(0);
            const sums: { lat: number; lng: number }[] = new Array(k).fill(null).map(() => ({ lat: 0, lng: 0 }));

            // 分配點到最近的中心
            points.forEach(point => {
                let minDistance = Infinity;
                let closestCenter = 0;

                centers.forEach((center, i) => {
                    const distance = this.calculateDistance(point, center);
                    if (distance < minDistance) {
                        minDistance = distance;
                        closestCenter = i;
                    }
                });

                assignments.push(closestCenter);
                sums[closestCenter].lat += point.lat;
                sums[closestCenter].lng += point.lng;
                counts[closestCenter]++;
            });

            // 更新中心點
            let converged = true;
            for (let i = 0; i < k; i++) {
                if (counts[i] > 0) {
                    const newCenter = {
                        lat: sums[i].lat / counts[i],
                        lng: sums[i].lng / counts[i],
                    };

                    if (this.calculateDistance(centers[i], newCenter) > 0.001) {
                        converged = false;
                    }

                    newCenters[i] = newCenter;
                } else {
                    newCenters[i] = centers[i];
                }
            }

            centers.splice(0, centers.length, ...newCenters);

            if (converged) break;
        }

        // 建立最終聚合
        clusters = [];
        for (let i = 0; i < k; i++) {
            if (counts[i] > 0) {
                const clusterPoints = points.filter((_, index) => assignments[index] === i);
                const bounds = this.calculateBounds(clusterPoints);

                clusters.push({
                    id: `cluster_${i}`,
                    center: centers[i],
                    count: counts[i],
                    bounds,
                });
            }
        }

        return clusters;
    }

    /**
     * 網格基礎聚合演算法
     */
    static gridCluster(points: MapPoint[], gridSize: number = 0.01): Cluster[] {
        if (points.length === 0) return [];

        // 計算邊界
        const lats = points.map(p => p.lat);
        const lngs = points.map(p => p.lng);
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);

        // 建立網格
        const grid: { [key: string]: MapPoint[] } = {};

        points.forEach(point => {
            const latIndex = Math.floor((point.lat - minLat) / gridSize);
            const lngIndex = Math.floor((point.lng - minLng) / gridSize);
            const key = `${latIndex}_${lngIndex}`;

            if (!grid[key]) {
                grid[key] = [];
            }
            grid[key].push(point);
        });

        // 建立聚合
        const clusters: Cluster[] = [];
        let clusterId = 0;

        Object.entries(grid).forEach(([key, cellPoints]) => {
            if (cellPoints.length > 0) {
                const centerLat = cellPoints.reduce((sum, p) => sum + p.lat, 0) / cellPoints.length;
                const centerLng = cellPoints.reduce((sum, p) => sum + p.lng, 0) / cellPoints.length;

                clusters.push({
                    id: `cluster_${clusterId}`,
                    center: { lat: centerLat, lng: centerLng },
                    count: cellPoints.length,
                    bounds: this.calculateBounds(cellPoints),
                });
                clusterId++;
            }
        });

        return clusters;
    }

    /**
     * 生成熱力圖資料
     */
    static generateHeatmapData(points: MapPoint[]): HeatmapPoint[] {
        return points.map(point => {
            const price = point.price || 0;
            const weight = Math.min(price / 50000, 1.0); // 標準化權重

            return {
                lat: point.lat,
                lng: point.lng,
                weight: Math.max(weight, 0.1), // 最小權重
                price_range: this.getPriceRange(price),
            };
        });
    }

    /**
     * 價格預測演算法 (簡化版)
     */
    static predictPrice(data: {
        lat: number;
        lng: number;
        area?: number;
        floor?: number;
        age?: number;
    }): { price: number; confidence: number; range: { min: number; max: number } } {
        // 台北市中心座標
        const centerLat = 25.0330;
        const centerLng = 121.5654;

        const basePrice = 20000;
        const areaFactor = (data.area || 20) * 1000;
        const locationFactor = this.getLocationFactor(data.lat, data.lng, centerLat, centerLng);
        const floorFactor = (data.floor || 1) * 500;
        const ageFactor = Math.max(0, (10 - (data.age || 5)) * 1000);

        const predictedPrice = basePrice + areaFactor + locationFactor + floorFactor + ageFactor;

        return {
            price: Math.round(predictedPrice),
            confidence: 0.75,
            range: {
                min: Math.round(predictedPrice * 0.9),
                max: Math.round(predictedPrice * 1.1),
            },
        };
    }

    /**
     * 智慧資料過濾
     */
    static smartFilter(points: MapPoint[], zoom: number, viewport: {
        north: number;
        south: number;
        east: number;
        west: number;
    }): MapPoint[] {
        // 根據視口過濾
        let filtered = points.filter(point =>
            point.lat >= viewport.south &&
            point.lat <= viewport.north &&
            point.lng >= viewport.west &&
            point.lng <= viewport.east
        );

        // 根據縮放級別決定顯示策略
        const maxPoints = this.getMaxPointsForZoom(zoom);

        if (filtered.length <= maxPoints) {
            return filtered;
        }

        // 使用網格取樣
        return this.gridSampling(filtered, maxPoints);
    }

    /**
     * 效能優化的視口更新
     */
    static optimizeViewportUpdate(
        currentPoints: MapPoint[],
        newViewport: { north: number; south: number; east: number; west: number },
        previousViewport: { north: number; south: number; east: number; west: number } | null
    ): { needsUpdate: boolean; strategy: 'full' | 'incremental' } {
        if (!previousViewport) {
            return { needsUpdate: true, strategy: 'full' };
        }

        // 計算視口變化程度
        const latChange = Math.abs(newViewport.north - previousViewport.north) +
                         Math.abs(newViewport.south - previousViewport.south);
        const lngChange = Math.abs(newViewport.east - previousViewport.east) +
                         Math.abs(newViewport.west - previousViewport.west);

        const changeThreshold = 0.01; // 約 1km

        if (latChange > changeThreshold || lngChange > changeThreshold) {
            return { needsUpdate: true, strategy: 'full' };
        }

        return { needsUpdate: false, strategy: 'incremental' };
    }

    // 輔助方法
    private static calculateDistance(point1: { lat: number; lng: number }, point2: { lat: number; lng: number }): number {
        const R = 6371; // 地球半徑 (km)
        const dLat = this.deg2rad(point2.lat - point1.lat);
        const dLng = this.deg2rad(point2.lng - point1.lng);

        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.deg2rad(point1.lat)) * Math.cos(this.deg2rad(point2.lat)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    private static deg2rad(deg: number): number {
        return deg * (Math.PI / 180);
    }

    private static getRandomIndices(length: number, k: number): number[] {
        const indices: number[] = [];
        const used = new Set<number>();

        while (indices.length < k && indices.length < length) {
            const index = Math.floor(Math.random() * length);
            if (!used.has(index)) {
                indices.push(index);
                used.add(index);
            }
        }

        return indices;
    }

    private static calculateBounds(points: MapPoint[]): {
        north: number;
        south: number;
        east: number;
        west: number;
    } {
        if (points.length === 0) {
            return { north: 0, south: 0, east: 0, west: 0 };
        }

        const lats = points.map(p => p.lat);
        const lngs = points.map(p => p.lng);

        return {
            north: Math.max(...lats),
            south: Math.min(...lats),
            east: Math.max(...lngs),
            west: Math.min(...lngs),
        };
    }

    private static getPriceRange(price: number): string {
        if (price < 20000) return '20000以下';
        if (price < 30000) return '20000-30000';
        if (price < 40000) return '30000-40000';
        return '40000以上';
    }

    private static getLocationFactor(lat: number, lng: number, centerLat: number, centerLng: number): number {
        const distance = this.calculateDistance({ lat, lng }, { lat: centerLat, lng: centerLng });
        return Math.max(0, 10000 - distance * 1000);
    }

    private static getMaxPointsForZoom(zoom: number): number {
        if (zoom <= 10) return 50;
        if (zoom <= 12) return 200;
        if (zoom <= 14) return 500;
        return 1000;
    }

    private static gridSampling(points: MapPoint[], maxPoints: number): MapPoint[] {
        const gridSize = Math.sqrt(points.length / maxPoints) * 0.01;
        const clusters = this.gridCluster(points, gridSize);

        // 從每個聚合中選擇代表點
        const sampled: MapPoint[] = [];
        clusters.forEach(cluster => {
            const clusterPoints = points.filter(point =>
                Math.abs(point.lat - cluster.center.lat) < gridSize &&
                Math.abs(point.lng - cluster.center.lng) < gridSize
            );

            if (clusterPoints.length > 0) {
                // 選擇聚合中心附近的點
                const representative = clusterPoints.reduce((closest, current) => {
                    const closestDist = this.calculateDistance(closest, cluster.center);
                    const currentDist = this.calculateDistance(current, cluster.center);
                    return currentDist < closestDist ? current : closest;
                });

                sampled.push(representative);
            }
        });

        return sampled.slice(0, maxPoints);
    }
}