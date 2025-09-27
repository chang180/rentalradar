<?php

namespace App\Support;

use Illuminate\Support\Arr;

class AdvancedPricePredictor
{
    public const MODEL_VERSION = 'v2.0-hostinger';
    public const TRAINED_AT = '2025-09-15';
    public const FEATURE_SET = [
        'area',
        'rooms',
        'floor',
        'age',
        'building_type',
        'location',
        'market_pressure',
    ];

    private const CBD_LAT = 25.0423;
    private const CBD_LNG = 121.5651;
    private const BASE_PRICE = 14500.0;

    /**
     * Predict the price for a single property payload.
     */
    public function predict(array $payload, array $options = []): array
    {
        $index = $payload['index'] ?? null;
        $id = $payload['id'] ?? $index;

        $lat = $this->toNullableFloat(Arr::get($payload, 'lat'));
        $lng = $this->toNullableFloat(Arr::get($payload, 'lng'));
        $area = $this->toNullableFloat(Arr::get($payload, 'area'));
        $floor = $this->toNullableInt(Arr::get($payload, 'floor'));
        $age = $this->toNullableFloat(Arr::get($payload, 'age'));
        $listedRent = $this->toNullableFloat(Arr::get($payload, 'rent_per_month'));
        $buildingType = strtolower(trim((string) Arr::get($payload, 'building_type', '')));
        $pattern = Arr::get($payload, 'pattern') ?? Arr::get($payload, 'room_type');
        $rooms = $this->resolveRooms($payload['rooms'] ?? null, $pattern);
        $district = Arr::get($payload, 'district');

        $areaComponent = $area !== null ? pow(max($area, 6.0), 0.92) * 950 : 0.0;
        $roomComponent = $rooms !== null ? min($rooms, 4) * 1200 : 0.0;

        $distanceKm = null;
        $locationMultiplier = 1.0;
        if ($lat !== null && $lng !== null) {
            $distanceKm = $this->haversine($lat, $lng, self::CBD_LAT, self::CBD_LNG);
            $locationBoost = 0.35 - log(1 + $distanceKm * 0.9) * 0.28;
            $locationMultiplier += $this->clamp($locationBoost, -0.35, 0.45);
            if ($district) {
                $locationMultiplier += $this->districtAdjustment($district);
            }
        }

        $floorMultiplier = 1.0;
        if ($floor !== null) {
            $floorMultiplier += min(max($floor - 1, 0), 14) * 0.015;
            if ($floor <= 2) {
                $floorMultiplier -= 0.02;
            }
        }

        $ageMultiplier = 1.0;
        if ($age !== null) {
            $agePenalty = $age <= 5 ? 0.0 : min(0.45, ($age - 5) * 0.012);
            $ageMultiplier = max(0.55, 1 - $agePenalty);
        }

        $amenityAdjustment = $this->buildingTypeAdjustment($buildingType);
        $marketModifier = $this->marketPressureModifier($listedRent, self::BASE_PRICE + $areaComponent + $roomComponent);

        $baseline = self::BASE_PRICE + $areaComponent + $roomComponent + $amenityAdjustment['absolute'];
        $rawPrice = $baseline
            * $locationMultiplier
            * $floorMultiplier
            * $ageMultiplier
            * (1 + $amenityAdjustment['multiplier'] + $marketModifier);

        $price = (int) round(max(6000, $rawPrice));

        $featureCount = $this->countFeatures([
            $area,
            $rooms,
            $floor,
            $age,
            $buildingType ? 1 : null,
            $lat,
            $lng,
        ]);

        $confidence = 0.58 + $featureCount * 0.08;
        if ($distanceKm !== null && $distanceKm > 12) {
            $confidence -= 0.05;
        }
        $confidence = $this->clamp($confidence, 0.55, 0.95);

        $volatility = max(0.08, 0.18 - $featureCount * 0.015);
        if ($distanceKm !== null) {
            $volatility += min(0.06, $distanceKm * 0.004);
        }

        $minRange = (int) round($price * (1 - $volatility));
        $maxRange = (int) round($price * (1 + $volatility));

        $breakdown = [
            'base' => (int) round(self::BASE_PRICE),
            'area_component' => (int) round($areaComponent),
            'room_component' => (int) round($roomComponent),
            'amenity_absolute' => (int) round($amenityAdjustment['absolute']),
            'location_multiplier' => round($locationMultiplier, 3),
            'floor_multiplier' => round($floorMultiplier, 3),
            'age_multiplier' => round($ageMultiplier, 3),
            'amenity_multiplier' => round($amenityAdjustment['multiplier'], 3),
            'market_modifier' => round($marketModifier, 3),
        ];

        $explanations = $this->buildExplanations([
            'area' => $area,
            'rooms' => $rooms,
            'floor_multiplier' => $floorMultiplier,
            'age_multiplier' => $ageMultiplier,
            'distance_km' => $distanceKm,
            'district' => $district,
            'building_type' => $buildingType,
        ], $confidence);

        return [
            'id' => $id,
            'index' => $index,
            'price' => $price,
            'confidence' => round($confidence, 2),
            'range' => [
                'min' => $minRange,
                'max' => $maxRange,
            ],
            'breakdown' => $breakdown,
            'explanations' => $explanations,
            'model_version' => self::MODEL_VERSION,
            'features_used' => $this->featuresUsed($area, $rooms, $floor, $age, $buildingType, $distanceKm),
        ];
    }

    /**
     * Predict multiple payloads preserving index order.
     */
    public function predictCollection(iterable $items, array $options = []): array
    {
        $predictions = [];
        foreach ($items as $index => $payload) {
            $payload['index'] = $payload['index'] ?? $index;
            $predictions[] = $this->predict($payload, $options);
        }

        return $predictions;
    }

    public function summarize(array $predictions): array
    {
        if ($predictions === []) {
            return [
                'count' => 0,
                'average_price' => 0,
                'median_price' => 0,
                'average_confidence' => 0,
                'min_price' => 0,
                'max_price' => 0,
            ];
        }

        $prices = array_column($predictions, 'price');
        $confidences = array_column($predictions, 'confidence');

        return [
            'count' => count($predictions),
            'average_price' => round(array_sum($prices) / max(count($prices), 1), 2),
            'median_price' => $this->median($prices),
            'average_confidence' => round(array_sum($confidences) / max(count($confidences), 1), 3),
            'min_price' => min($prices),
            'max_price' => max($prices),
        ];
    }

    private function marketPressureModifier(?float $listedRent, float $baseline): float
    {
        if ($listedRent === null || $listedRent <= 0) {
            return 0.0;
        }

        $delta = ($listedRent - $baseline) / max($baseline, 1);
        return $this->clamp($delta * 0.45, -0.2, 0.35);
    }

    private function buildingTypeAdjustment(string $buildingType): array
    {
        return match (true) {
            str_contains($buildingType, '電梯') => ['absolute' => 1200.0, 'multiplier' => 0.06],
            str_contains($buildingType, '套房') => ['absolute' => 800.0, 'multiplier' => 0.03],
            str_contains($buildingType, '華廈') => ['absolute' => 1500.0, 'multiplier' => 0.05],
            str_contains($buildingType, '大樓') => ['absolute' => 2000.0, 'multiplier' => 0.07],
            default => ['absolute' => 0.0, 'multiplier' => 0.0],
        };
    }

    private function resolveRooms($rooms, $pattern): ?int
    {
        if (is_numeric($rooms)) {
            return max(0, (int) $rooms);
        }

        if (is_string($pattern)) {
            if (preg_match('/(?:(\d+)房)/u', $pattern, $matches)) {
                return max(0, (int) $matches[1]);
            }
        }

        return null;
    }

    private function featuresUsed(?float $area, ?int $rooms, ?int $floor, ?float $age, ?string $buildingType, ?float $distanceKm): array
    {
        return [
            'area' => $area !== null,
            'rooms' => $rooms !== null,
            'floor' => $floor !== null,
            'age' => $age !== null,
            'building_type' => !empty($buildingType),
            'location' => $distanceKm !== null,
        ];
    }

    private function buildExplanations(array $context, float $confidence): array
    {
        $messages = [];
        if ($context['area'] !== null) {
            $messages[] = '面積 ' . round($context['area'], 1) . ' 坪作為主要估價基礎';
        }
        if ($context['rooms'] !== null) {
            $messages[] = '房數 ' . $context['rooms'] . ' 間帶來額外租金需求';
        }
        if ($context['floor_multiplier'] !== null && $context['floor_multiplier'] > 1.0) {
            $messages[] = '樓層偏高帶來景觀與通風加成';
        }
        if ($context['age_multiplier'] !== null && $context['age_multiplier'] < 1.0) {
            $messages[] = '建物年齡造成折價，同步反映於乘數 ' . $context['age_multiplier'];
        }
        if ($context['distance_km'] !== null) {
            $messages[] = '距離市中心約 ' . round($context['distance_km'], 1) . ' 公里';
        }
        if ($context['district']) {
            $messages[] = '行政區 ' . $context['district'] . ' 市場熱度已納入運算';
        }
        if ($context['building_type']) {
            $messages[] = '建物類型 ' . $context['building_type'] . ' 套用對應便利與管理加權';
        }
        $messages[] = '信心指數約 ' . round($confidence * 100, 1) . '%，依賴可取得的特徵數量調整';

        return array_values(array_unique($messages));
    }

    private function districtAdjustment(?string $district): float
    {
        if (!$district) {
            return 0.0;
        }
        $district = mb_strtolower($district);
        return match (true) {
            str_contains($district, '信義') => 0.08,
            str_contains($district, '大安') => 0.07,
            str_contains($district, '中山') => 0.05,
            str_contains($district, '內湖') => 0.03,
            str_contains($district, '文山') => -0.02,
            str_contains($district, '萬華') => -0.015,
            default => 0.0,
        };
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return (float) (($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function countFeatures(array $features): int
    {
        return count(array_filter($features, static fn($value) => $value !== null));
    }

    private function toNullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $a = sin($dLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }
}
