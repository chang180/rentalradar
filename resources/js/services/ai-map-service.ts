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
        indices.forEach((index) => {
            centers.push({ lat: points[index].lat, lng: points[index].lng });
        });

        const maxIterations = 10;
        let clusters: Cluster[] = [];

        for (let iter = 0; iter < maxIterations; iter++) {
            const assignments: number[] = [];
            const newCenters: { lat: number; lng: number }[] = [];
            const counts: number[] = new Array(k).fill(0);
            const sums: { lat: number; lng: number }[] = new Array(k)
                .fill(null)
                .map(() => ({ lat: 0, lng: 0 }));

            // 分配點到最近的中心
            points.forEach((point) => {
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
                const clusterPoints = points.filter(
                    (_, index) => assignments[index] === i,
                );
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
        const lats = points.map((p) => p.lat);
        const lngs = points.map((p) => p.lng);
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);

        // 建立網格
        const grid: { [key: string]: MapPoint[] } = {};

        points.forEach((point) => {
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
                const centerLat =
                    cellPoints.reduce((sum, p) => sum + p.lat, 0) /
                    cellPoints.length;
                const centerLng =
                    cellPoints.reduce((sum, p) => sum + p.lng, 0) /
                    cellPoints.length;

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
        return points.map((point) => {
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
        lat?: number;
        lng?: number;
        area?: number;
        floor?: number;
        age?: number;
        rent_per_month?: number;
        building_type?: string;
        rooms?: number;
        pattern?: string;
        district?: string;
    }): {
        price: number;
        confidence: number;
        range: { min: number; max: number };
        modelVersion: string;
        breakdown: Record<string, number>;
        explanations: string[];
    } {
        const BASE_PRICE = 14500;
        const area =
            typeof data.area === 'number' ? Math.max(data.area, 6) : undefined;
        const rooms = this.resolveRooms(data.rooms, data.pattern);
        const areaComponent = area ? Math.pow(area, 0.92) * 950 : 0;
        const roomComponent = rooms ? Math.min(rooms, 4) * 1200 : 0;

        let locationMultiplier = 1;
        let distanceKm: number | undefined;
        if (typeof data.lat === 'number' && typeof data.lng === 'number') {
            distanceKm = this.haversine(data.lat, data.lng, 25.0423, 121.5651);
            const locationBoost = 0.35 - Math.log(1 + distanceKm * 0.9) * 0.28;
            locationMultiplier += this.clamp(locationBoost, -0.35, 0.45);
            if (data.district) {
                locationMultiplier += this.districtAdjustment(data.district);
            }
        }

        let floorMultiplier = 1;
        if (typeof data.floor === 'number') {
            const normalizedFloor = Math.max(1, Math.min(data.floor, 25));
            floorMultiplier +=
                Math.min(Math.max(normalizedFloor - 1, 0), 14) * 0.015;
            if (normalizedFloor <= 2) {
                floorMultiplier -= 0.02;
            }
        }

        let ageMultiplier = 1;
        if (typeof data.age === 'number') {
            const agePenalty =
                data.age <= 5 ? 0 : Math.min(0.45, (data.age - 5) * 0.012);
            ageMultiplier = Math.max(0.55, 1 - agePenalty);
        }

        const amenityAdjustment = this.buildingTypeAdjustment(
            data.building_type,
        );
        const baseline =
            BASE_PRICE +
            areaComponent +
            roomComponent +
            amenityAdjustment.absolute;
        const marketModifier = this.marketPressureModifier(
            typeof data.rent_per_month === 'number'
                ? data.rent_per_month
                : undefined,
            baseline,
        );

        const rawPrice =
            baseline *
            locationMultiplier *
            floorMultiplier *
            ageMultiplier *
            (1 + amenityAdjustment.multiplier + marketModifier);
        const price = Math.round(Math.max(6000, rawPrice));

        const featureCount = [
            area,
            rooms,
            data.floor,
            data.age,
            data.building_type,
            distanceKm,
        ].filter((value) => value !== undefined && value !== null).length;
        let confidence = 0.58 + featureCount * 0.08;
        if (typeof distanceKm === 'number' && distanceKm > 12) {
            confidence -= 0.05;
        }
        confidence = this.clamp(confidence, 0.55, 0.95);

        let volatility = Math.max(0.08, 0.18 - featureCount * 0.015);
        if (typeof distanceKm === 'number') {
            volatility += Math.min(0.06, distanceKm * 0.004);
        }

        const range = {
            min: Math.round(price * (1 - volatility)),
            max: Math.round(price * (1 + volatility)),
        };

        const breakdown = {
            base: Math.round(BASE_PRICE),
            area_component: Math.round(areaComponent),
            room_component: Math.round(roomComponent),
            amenity_absolute: Math.round(amenityAdjustment.absolute),
            location_multiplier: parseFloat(locationMultiplier.toFixed(3)),
            floor_multiplier: parseFloat(floorMultiplier.toFixed(3)),
            age_multiplier: parseFloat(ageMultiplier.toFixed(3)),
            amenity_multiplier: parseFloat(
                amenityAdjustment.multiplier.toFixed(3),
            ),
            market_modifier: parseFloat(marketModifier.toFixed(3)),
        };

        const explanations: string[] = [];
        if (area) {
            explanations.push(`面積 ${area.toFixed(1)} 坪提供主要估價基礎`);
        }
        if (rooms) {
            explanations.push(`房數 ${rooms} 間提高租賃需求`);
        }
        if (floorMultiplier > 1) {
            explanations.push('樓層高度帶來景觀加成');
        }
        if (ageMultiplier < 1) {
            explanations.push('建物年齡產生折價影響');
        }
        if (typeof distanceKm === 'number') {
            explanations.push(`距離市中心約 ${distanceKm.toFixed(1)} 公里`);
        }
        if (data.district) {
            explanations.push(`行政區 ${data.district} 市場熱度已納入運算`);
        }

        explanations.push(`信心指數約 ${(confidence * 100).toFixed(1)}%`);

        return {
            price,
            confidence: parseFloat(confidence.toFixed(2)),
            range,
            modelVersion: 'v2.0-hostinger',
            breakdown,
            explanations: Array.from(new Set(explanations)),
        };
    }

    /**
     * 智慧資料過濾
     */
    static smartFilter(
        points: MapPoint[],
        zoom: number,
        viewport: {
            north: number;
            south: number;
            east: number;
            west: number;
        },
    ): MapPoint[] {
        // 根據視口過濾
        let filtered = points.filter(
            (point) =>
                point.lat >= viewport.south &&
                point.lat <= viewport.north &&
                point.lng >= viewport.west &&
                point.lng <= viewport.east,
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
        newViewport: {
            north: number;
            south: number;
            east: number;
            west: number;
        },
        previousViewport: {
            north: number;
            south: number;
            east: number;
            west: number;
        } | null,
    ): { needsUpdate: boolean; strategy: 'full' | 'incremental' } {
        if (!previousViewport) {
            return { needsUpdate: true, strategy: 'full' };
        }

        // 計算視口變化程度
        const latChange =
            Math.abs(newViewport.north - previousViewport.north) +
            Math.abs(newViewport.south - previousViewport.south);
        const lngChange =
            Math.abs(newViewport.east - previousViewport.east) +
            Math.abs(newViewport.west - previousViewport.west);

        const changeThreshold = 0.01; // 約 1km

        if (latChange > changeThreshold || lngChange > changeThreshold) {
            return { needsUpdate: true, strategy: 'full' };
        }

        return { needsUpdate: false, strategy: 'incremental' };
    }

    // 輔助方法
    private static calculateDistance(
        point1: { lat: number; lng: number },
        point2: { lat: number; lng: number },
    ): number {
        const R = 6371; // 地球半徑 (km)
        const dLat = this.deg2rad(point2.lat - point1.lat);
        const dLng = this.deg2rad(point2.lng - point1.lng);

        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(this.deg2rad(point1.lat)) *
                Math.cos(this.deg2rad(point2.lat)) *
                Math.sin(dLng / 2) *
                Math.sin(dLng / 2);

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

        const lats = points.map((p) => p.lat);
        const lngs = points.map((p) => p.lng);

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

    private static getLocationFactor(
        lat: number,
        lng: number,
        centerLat: number,
        centerLng: number,
    ): number {
        const distance = this.calculateDistance(
            { lat, lng },
            { lat: centerLat, lng: centerLng },
        );
        return Math.max(0, 10000 - distance * 1000);
    }

    private static marketPressureModifier(
        listedRent: number | undefined,
        baseline: number,
    ): number {
        if (!listedRent || listedRent <= 0) return 0;
        const delta = (listedRent - baseline) / Math.max(baseline, 1);
        return this.clamp(delta * 0.45, -0.2, 0.35);
    }

    private static buildingTypeAdjustment(buildingType?: string): {
        absolute: number;
        multiplier: number;
    } {
        const type = (buildingType || '').toLowerCase();
        if (!type) {
            return { absolute: 0, multiplier: 0 };
        }

        if (type.includes('電梯')) return { absolute: 1200, multiplier: 0.06 };
        if (type.includes('套房')) return { absolute: 800, multiplier: 0.03 };
        if (type.includes('華廈')) return { absolute: 1500, multiplier: 0.05 };
        if (type.includes('大樓')) return { absolute: 2000, multiplier: 0.07 };
        return { absolute: 0, multiplier: 0 };
    }

    private static districtAdjustment(district: string): number {
        const normalized = district.toLowerCase();
        if (normalized.includes('信義')) return 0.08;
        if (normalized.includes('大安')) return 0.07;
        if (normalized.includes('中山')) return 0.05;
        if (normalized.includes('內湖')) return 0.03;
        if (normalized.includes('文山')) return -0.02;
        if (normalized.includes('萬華')) return -0.015;
        return 0;
    }

    private static resolveRooms(
        rooms?: number,
        pattern?: string,
    ): number | undefined {
        if (typeof rooms === 'number' && rooms >= 0) {
            return Math.floor(rooms);
        }

        if (typeof pattern === 'string') {
            const match = pattern.match(/(\d+)房/u);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        return undefined;
    }

    private static haversine(
        lat1: number,
        lng1: number,
        lat2: number,
        lng2: number,
    ): number {
        const R = 6371.0088;
        const dLat = this.deg2rad(lat2 - lat1);
        const dLng = this.deg2rad(lng2 - lng1);
        const lat1Rad = this.deg2rad(lat1);
        const lat2Rad = this.deg2rad(lat2);

        const a =
            Math.sin(dLat / 2) ** 2 +
            Math.cos(lat1Rad) * Math.cos(lat2Rad) * Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    private static clamp(value: number, min: number, max: number): number {
        return Math.min(max, Math.max(min, value));
    }

    private static getMaxPointsForZoom(zoom: number): number {
        if (zoom <= 10) return 50;
        if (zoom <= 12) return 200;
        if (zoom <= 14) return 500;
        return 1000;
    }

    private static gridSampling(
        points: MapPoint[],
        maxPoints: number,
    ): MapPoint[] {
        const gridSize = Math.sqrt(points.length / maxPoints) * 0.01;
        const clusters = this.gridCluster(points, gridSize);

        // 從每個聚合中選擇代表點
        const sampled: MapPoint[] = [];
        clusters.forEach((cluster) => {
            const clusterPoints = points.filter(
                (point) =>
                    Math.abs(point.lat - cluster.center.lat) < gridSize &&
                    Math.abs(point.lng - cluster.center.lng) < gridSize,
            );

            if (clusterPoints.length > 0) {
                // 選擇聚合中心附近的點
                const representative = clusterPoints.reduce(
                    (closest, current) => {
                        const closestDist = this.calculateDistance(
                            closest,
                            cluster.center,
                        );
                        const currentDist = this.calculateDistance(
                            current,
                            cluster.center,
                        );
                        return currentDist < closestDist ? current : closest;
                    },
                );

                sampled.push(representative);
            }
        });

        return sampled.slice(0, maxPoints);
    }
}
