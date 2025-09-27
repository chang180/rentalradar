/**
 * 智慧標記聚合演算法 (JavaScript 實現)
 * 適用於 Hostinger 共享空間
 */

export class ClusteringAlgorithm {
    constructor() {
        this.earthRadius = 6371; // 地球半徑 (km)
        this.maxIterations = 15;
        this.centerShiftToleranceKm = 0.025; // ~25 公尺
    }

    /**
     * K-means 聚合演算法（含決定性初始化與半徑/密度統計）
     */
    kmeansClustering(rawCoordinates, k = 10, options = {}) {
        const coordinates = this.normalizePoints(rawCoordinates);
        const pointCount = coordinates.length;
        if (pointCount === 0) return [];

        const maxIterations = options.maxIterations || this.maxIterations;
        const shiftTolerance = options.centerShiftToleranceKm || this.centerShiftToleranceKm;
        const clusterCount = Math.max(1, Math.min(k, pointCount));

        const centers = this.initializeCentroids(coordinates, clusterCount);
        const centerRadians = centers.map(center => [
            this.toRadians(center[0]),
            this.toRadians(center[1])
        ]);

        const latRadians = coordinates.map(point => this.toRadians(point[0]));
        const lngRadians = coordinates.map(point => this.toRadians(point[1]));

        const assignments = new Array(pointCount).fill(-1);
        const clusters = [];

        for (let iter = 0; iter < maxIterations; iter++) {
            const newCenters = Array.from({ length: clusterCount }, () => [0, 0]);
            const counts = new Array(clusterCount).fill(0);
            const errors = new Array(pointCount).fill(0);
            let assignmentChanged = false;

            for (let index = 0; index < pointCount; index++) {
                let minDistance = Number.POSITIVE_INFINITY;
                let closestCluster = 0;

                for (let candidate = 0; candidate < clusterCount; candidate++) {
                    const distance = this.haversineFromRadians(
                        latRadians[index],
                        lngRadians[index],
                        centerRadians[candidate][0],
                        centerRadians[candidate][1]
                    );

                    if (distance < minDistance) {
                        minDistance = distance;
                        closestCluster = candidate;
                    }
                }

                if (assignments[index] !== closestCluster) {
                    assignmentChanged = true;
                    assignments[index] = closestCluster;
                }

                errors[index] = minDistance;
                newCenters[closestCluster][0] += coordinates[index][0];
                newCenters[closestCluster][1] += coordinates[index][1];
                counts[closestCluster]++;
            }

            let maxShift = 0;
            for (let clusterIndex = 0; clusterIndex < clusterCount; clusterIndex++) {
                if (counts[clusterIndex] === 0) {
                    const reseed = this.findReseedCandidate(errors, assignments, clusterIndex);
                    if (!reseed) continue;

                    const { index, previousCluster } = reseed;
                    if (previousCluster !== null && previousCluster >= 0) {
                        counts[previousCluster] = Math.max(0, counts[previousCluster] - 1);
                        newCenters[previousCluster][0] -= coordinates[index][0];
                        newCenters[previousCluster][1] -= coordinates[index][1];
                    }

                    centers[clusterIndex] = coordinates[index];
                    centerRadians[clusterIndex] = [latRadians[index], lngRadians[index]];
                    assignments[index] = clusterIndex;
                    newCenters[clusterIndex] = [...coordinates[index]];
                    counts[clusterIndex] = 1;
                    errors[index] = 0;
                    assignmentChanged = true;
                    continue;
                }

                const newLat = newCenters[clusterIndex][0] / counts[clusterIndex];
                const newLng = newCenters[clusterIndex][1] / counts[clusterIndex];
                const shift = this.haversineFromRadians(
                    centerRadians[clusterIndex][0],
                    centerRadians[clusterIndex][1],
                    this.toRadians(newLat),
                    this.toRadians(newLng)
                );

                centers[clusterIndex] = [newLat, newLng];
                centerRadians[clusterIndex] = [this.toRadians(newLat), this.toRadians(newLng)];
                maxShift = Math.max(maxShift, shift);
            }

            if (!assignmentChanged && maxShift <= shiftTolerance) {
                break;
            }
        }

        const memberPoints = Array.from({ length: clusterCount }, () => []);
        assignments.forEach((clusterIndex, pointIndex) => {
            if (clusterIndex >= 0) {
                memberPoints[clusterIndex].push(coordinates[pointIndex]);
            }
        });

        memberPoints.forEach((points, clusterIndex) => {
            if (points.length === 0) return;
            clusters.push(this.formatCluster(clusterIndex, centers[clusterIndex], points));
        });

        return clusters;
    }

    /**
     * 網格聚合演算法
     */
    gridClustering(rawCoordinates, gridSizeKm = 1) {
        const coordinates = this.normalizePoints(rawCoordinates);
        if (coordinates.length === 0) return [];

        const bounds = this.calculateBounds(coordinates);
        const latRange = Math.max(0.0001, bounds.north - bounds.south);
        const lngRange = Math.max(0.0001, bounds.east - bounds.west);
        const dynamicSize = this.resolveGridSize(Math.max(latRange, lngRange), coordinates.length, gridSizeKm);

        const grid = new Map();
        coordinates.forEach(point => {
            const latIndex = Math.floor((point[0] - bounds.south) / dynamicSize);
            const lngIndex = Math.floor((point[1] - bounds.west) / dynamicSize);
            const key = `${latIndex}_${lngIndex}`;

            if (!grid.has(key)) {
                grid.set(key, []);
            }
            grid.get(key).push(point);
        });

        const clusters = [];
        let clusterId = 0;
        for (const points of grid.values()) {
            if (points.length === 0) continue;
            const centerLat = points.reduce((sum, point) => sum + point[0], 0) / points.length;
            const centerLng = points.reduce((sum, point) => sum + point[1], 0) / points.length;
            clusters.push(this.formatCluster(clusterId++, [centerLat, centerLng], points));
        }

        return clusters;
    }

    /**
     * 計算兩點間距離 (Haversine 公式)
     */
    calculateDistance(point1, point2) {
        const [lat1, lng1] = point1;
        const [lat2, lng2] = point2;

        const dLat = this.toRadians(lat2 - lat1);
        const dLng = this.toRadians(lng2 - lng1);
        
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + 
                  Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) * 
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        
        return this.earthRadius * c;
    }

    /**
     * 計算邊界
     */
    calculateBounds(points) {
        if (points.length === 0) {
            return { north: 0, south: 0, east: 0, west: 0 };
        }

        const lats = points.map(point => point[0]);
        const lngs = points.map(point => point[1]);

        return {
            north: Math.max(...lats),
            south: Math.min(...lats),
            east: Math.max(...lngs),
            west: Math.min(...lngs)
        };
    }

    /**
     * 角度轉弧度
     */
    toRadians(degrees) {
        return degrees * (Math.PI / 180);
    }

    /**
     * 根據弧度座標計算 Haversine 距離
     */
    haversineFromRadians(lat1, lng1, lat2, lng2) {
        const dLat = lat2 - lat1;
        const dLng = lng2 - lng1;
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return this.earthRadius * c;
    }

    /**
     * 格式化聚合輸出，包含半徑與密度
     */
    formatCluster(id, center, points) {
        const bounds = this.calculateBounds(points);
        const radius = this.calculateClusterRadius(center, points);
        const density = radius > 0 ? points.length / (Math.PI * radius ** 2) : null;

        return {
            id: `cluster_${id}`,
            center: {
                lat: Number(center[0].toFixed(6)),
                lng: Number(center[1].toFixed(6))
            },
            count: points.length,
            bounds,
            radius_km: Number(radius.toFixed(4)),
            density: density === null ? null : Number(density.toFixed(4))
        };
    }

    /**
     * 正規化輸入座標，忽略無效資料
     */
    normalizePoints(points) {
        return points
            .map(point => {
                if (Array.isArray(point)) {
                    return [Number(point[0]), Number(point[1])];
                }
                if (point && typeof point === 'object') {
                    const lat = Number(point.lat ?? point.latitude);
                    const lng = Number(point.lng ?? point.longitude);
                    return [lat, lng];
                }
                return null;
            })
            .filter(point => Array.isArray(point) && isFinite(point[0]) && isFinite(point[1]));
    }

    /**
     * 決定性初始化中心點（依緯度排序取樣）
     */
    initializeCentroids(coordinates, k) {
        const sorted = [...coordinates].sort((a, b) => (a[0] - b[0]) || (a[1] - b[1]));
        const step = Math.max(1, Math.floor(sorted.length / k));
        const centers = [];
        for (let i = 0; i < k; i++) {
            const index = Math.min(i * step, sorted.length - 1);
            centers.push([...sorted[index]]);
        }
        return centers;
    }

    /**
     * 找出誤差最大的資料點作為 reseed 候選
     */
    findReseedCandidate(errors, assignments, excludedCluster) {
        let maxError = -1;
        let index = null;
        let previousCluster = null;

        errors.forEach((error, idx) => {
            if (assignments[idx] === excludedCluster) return;
            if (error > maxError) {
                maxError = error;
                index = idx;
                previousCluster = assignments[idx];
            }
        });

        if (index === null) return null;
        return { index, previousCluster };
    }

    /**
     * 計算聚合最大半徑
     */
    calculateClusterRadius(center, points) {
        const latCenter = this.toRadians(center[0]);
        const lngCenter = this.toRadians(center[1]);
        let maxDistance = 0;

        points.forEach(point => {
            const distance = this.haversineFromRadians(
                latCenter,
                lngCenter,
                this.toRadians(point[0]),
                this.toRadians(point[1])
            );
            maxDistance = Math.max(maxDistance, distance);
        });

        return maxDistance;
    }

    /**
     * 依樣本密度調整網格長度（預設以公里計）
     */
    resolveGridSize(rangeDegrees, pointCount, fallbackKm = 1) {
        const degreeApproxKm = 111; // 緯度上 1 度約 111 公里
        const fallbackDegrees = fallbackKm / degreeApproxKm;

        if (pointCount <= 1) {
            return Math.max(0.0005, rangeDegrees || fallbackDegrees);
        }

        const dynamic = rangeDegrees / Math.max(1, Math.sqrt(pointCount));
        return Math.max(0.0005, Math.min(0.05, dynamic || fallbackDegrees));
    }

    /**
     * 產出品質摘要，協助前端 UI 反映
     */
    summarize(clusters) {
        if (!clusters.length) {
            return {
                clusterCount: 0,
                avgRadiusKm: 0,
                medianDensity: 0
            };
        }

        const radii = clusters.map(cluster => cluster.radius_km || 0);
        const densities = clusters
            .map(cluster => cluster.density)
            .filter(value => typeof value === 'number');

        return {
            clusterCount: clusters.length,
            avgRadiusKm: Number((radii.reduce((sum, value) => sum + value, 0) / radii.length).toFixed(4)),
            medianDensity: densities.length ? Number(this.median(densities).toFixed(4)) : 0
        };
    }

    median(values) {
        const sorted = [...values].sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        if (sorted.length % 2 === 1) {
            return sorted[middle];
        }
        return (sorted[middle - 1] + sorted[middle]) / 2;
    }
}
