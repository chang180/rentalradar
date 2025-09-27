<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecommendationEngine
{
    private const RECOMMENDATION_CACHE_TTL = 1800; // 30分鐘

    private const MAX_RECOMMENDATIONS = 20;

    public function generatePersonalizedRecommendations(User $user, int $limit = 10): array
    {
        $cacheKey = "personalized_recommendations_user_{$user->id}_{$limit}";

        return Cache::remember($cacheKey, self::RECOMMENDATION_CACHE_TTL, function () use ($user, $limit) {
            try {
                // 1. 分析使用者行為
                $userProfile = $this->analyzeUserBehavior($user);

                // 2. 計算相似度
                $similarProperties = $this->findSimilarProperties($userProfile);

                // 3. 生成推薦
                $recommendations = $this->generateRecommendations($userProfile, $similarProperties, $limit);

                // 4. 儲存推薦記錄
                $this->saveRecommendations($user, $recommendations);

                return [
                    'status' => 'success',
                    'recommendations' => $recommendations,
                    'user_profile' => $userProfile,
                    'generated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                Log::error('Personalized recommendation generation failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Recommendation generation failed: '.$e->getMessage(),
                ];
            }
        });
    }

    public function getTrendingRecommendations(int $limit = 10): array
    {
        $cacheKey = "trending_recommendations_{$limit}";

        return Cache::remember($cacheKey, self::RECOMMENDATION_CACHE_TTL, function () use ($limit) {
            try {
                $properties = Property::query()
                    ->whereNotNull('rent_per_month')
                    ->whereNotNull('total_floor_area')
                    ->where('rent_per_month', '>', 0)
                    ->where('total_floor_area', '>', 0)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit * 2)
                    ->get();

                $recommendations = $this->rankProperties($properties, $limit);

                return [
                    'status' => 'success',
                    'recommendations' => $recommendations,
                    'method' => 'trending',
                ];

            } catch (\Exception $e) {
                Log::error('Trending recommendation failed', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Trending recommendation failed: '.$e->getMessage(),
                ];
            }
        });
    }

    private function analyzeUserBehavior(User $user): array
    {
        return [
            'preferred_districts' => $this->getPreferredDistricts($user),
            'price_range' => $this->getPriceRange($user),
            'property_types' => $this->getPreferredPropertyTypes($user),
            'area_preferences' => $this->getAreaPreferences($user),
        ];
    }

    private function findSimilarProperties(array $userProfile): array
    {
        $query = Property::query()
            ->whereNotNull('rent_per_month')
            ->whereNotNull('total_floor_area')
            ->where('rent_per_month', '>', 0)
            ->where('total_floor_area', '>', 0);

        if (! empty($userProfile['preferred_districts'])) {
            $query->whereIn('district', $userProfile['preferred_districts']);
        }

        if (! empty($userProfile['property_types'])) {
            $query->whereIn('building_type', $userProfile['property_types']);
        }

        if (! empty($userProfile['price_range'])) {
            $query->whereBetween('rent_per_month', $userProfile['price_range']);
        }

        return $query->limit(self::MAX_RECOMMENDATIONS)->get()->toArray();
    }

    private function generateRecommendations(array $userProfile, array $properties, int $limit): array
    {
        $recommendations = [];

        foreach ($properties as $property) {
            $score = $this->calculateRecommendationScore($userProfile, $property);

            if ($score > 0.3) {
                $recommendations[] = [
                    'property_id' => $property['id'],
                    'title' => $this->generatePropertyTitle($property),
                    'summary' => $this->generatePropertySummary($property),
                    'price' => $property['rent_per_month'],
                    'district' => $property['district'],
                    'score' => round($score * 100, 2),
                    'reasons' => $this->generateRecommendationReasons($userProfile, $property),
                    'metadata' => [
                        'area' => $property['total_floor_area'],
                        'rooms' => $this->extractRoomCount($property['compartment_pattern']),
                        'building_type' => $property['building_type'],
                        'price_per_sqm' => $property['total_floor_area'] > 0
                            ? round($property['rent_per_month'] / $property['total_floor_area'], 2)
                            : 0,
                    ],
                ];
            }
        }

        usort($recommendations, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($recommendations, 0, $limit);
    }

    private function calculateRecommendationScore(array $userProfile, array $property): float
    {
        $score = 0;
        $totalWeight = 0;

        // 地區匹配 (30%)
        if (in_array($property['district'], $userProfile['preferred_districts'] ?? [])) {
            $score += 0.3;
        }
        $totalWeight += 0.3;

        // 價格範圍匹配 (25%)
        if (! empty($userProfile['price_range'])) {
            $price = $property['rent_per_month'];
            $minPrice = $userProfile['price_range'][0];
            $maxPrice = $userProfile['price_range'][1];

            if ($price >= $minPrice && $price <= $maxPrice) {
                $score += 0.25;
            }
        }
        $totalWeight += 0.25;

        // 建築類型匹配 (20%)
        if (in_array($property['building_type'], $userProfile['property_types'] ?? [])) {
            $score += 0.2;
        }
        $totalWeight += 0.2;

        // 面積偏好匹配 (15%)
        if (! empty($userProfile['area_preferences'])) {
            $area = $property['total_floor_area'];
            $preferredArea = $userProfile['area_preferences']['preferred'];
            $tolerance = $userProfile['area_preferences']['tolerance'] ?? 10;

            if (abs($area - $preferredArea) <= $tolerance) {
                $score += 0.15;
            }
        }
        $totalWeight += 0.15;

        // 其他因素 (10%)
        $score += 0.1;
        $totalWeight += 0.1;

        return $totalWeight > 0 ? $score / $totalWeight : 0;
    }

    private function generateRecommendationReasons(array $userProfile, array $property): array
    {
        $reasons = [];

        if (in_array($property['district'], $userProfile['preferred_districts'] ?? [])) {
            $reasons[] = "位於您偏好的 {$property['district']}";
        }

        if (! empty($userProfile['price_range'])) {
            $price = $property['rent_per_month'];
            $minPrice = $userProfile['price_range'][0];
            $maxPrice = $userProfile['price_range'][1];

            if ($price >= $minPrice && $price <= $maxPrice) {
                $reasons[] = '價格符合您的預算範圍';
            }
        }

        if (in_array($property['building_type'], $userProfile['property_types'] ?? [])) {
            $reasons[] = "符合您偏好的 {$property['building_type']} 類型";
        }

        if (empty($reasons)) {
            $reasons[] = '基於市場熱度和價格合理性推薦';
        }

        return $reasons;
    }

    private function getPreferredDistricts(User $user): array
    {
        return ['中正區', '大安區', '信義區'];
    }

    private function getPriceRange(User $user): array
    {
        return [15000, 35000];
    }

    private function getPreferredPropertyTypes(User $user): array
    {
        return ['住宅大樓', '華廈', '公寓'];
    }

    private function getAreaPreferences(User $user): array
    {
        return [
            'preferred' => 25,
            'tolerance' => 10,
        ];
    }

    private function rankProperties($properties, int $limit): array
    {
        $rankedProperties = [];

        foreach ($properties as $property) {
            $score = $this->calculateTrendingScore($property);

            $rankedProperties[] = [
                'property_id' => $property->id,
                'title' => $this->generatePropertyTitle($property->toArray()),
                'summary' => $this->generatePropertySummary($property->toArray()),
                'price' => $property->rent_per_month,
                'district' => $property->district,
                'score' => $score,
                'reasons' => ['熱門推薦', '價格合理', '位置優越'],
                'metadata' => [
                    'area' => $property->total_floor_area,
                    'rooms' => $this->extractRoomCount($property->compartment_pattern),
                    'building_type' => $property->building_type,
                    'price_per_sqm' => $property->total_floor_area > 0
                        ? round($property->rent_per_month / $property->total_floor_area, 2)
                        : 0,
                ],
            ];
        }

        usort($rankedProperties, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($rankedProperties, 0, $limit);
    }

    private function calculateTrendingScore($property): float
    {
        $score = 0;

        $pricePerSqm = $property->total_floor_area > 0
            ? $property->rent_per_month / $property->total_floor_area
            : 0;

        if ($pricePerSqm > 0 && $pricePerSqm < 2000) {
            $score += 0.4;
        }

        $popularDistricts = ['大安區', '信義區', '中正區', '松山區'];
        if (in_array($property->district, $popularDistricts)) {
            $score += 0.3;
        }

        $popularTypes = ['住宅大樓', '華廈'];
        if (in_array($property->building_type, $popularTypes)) {
            $score += 0.2;
        }

        if ($property->total_floor_area >= 15 && $property->total_floor_area <= 50) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    private function generatePropertyTitle(array $property): string
    {
        $district = $property['district'] ?? '';
        $rooms = $this->extractRoomCount($property['compartment_pattern'] ?? '');
        $area = $property['total_floor_area'] ?? 0;

        return "{$district} {$rooms}房{$area}坪 優質租屋";
    }

    private function generatePropertySummary(array $property): string
    {
        $district = $property['district'] ?? '';
        $buildingType = $property['building_type'] ?? '';
        $price = $property['rent_per_month'] ?? 0;

        return "位於{$district}的{$buildingType}，月租金{$price}元，交通便利，生活機能完善。";
    }

    private function extractRoomCount(?string $pattern): int
    {
        if (! $pattern) {
            return 1;
        }

        if (preg_match('/(\d+)房/', $pattern, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function saveRecommendations(User $user, array $recommendations): void
    {
        try {
            foreach ($recommendations as $recommendation) {
                Recommendation::create([
                    'property_id' => $recommendation['property_id'],
                    'type' => 'personalized',
                    'title' => $recommendation['title'],
                    'summary' => $recommendation['summary'],
                    'reasons' => $recommendation['reasons'],
                    'score' => $recommendation['score'],
                    'metadata' => $recommendation['metadata'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save recommendations', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
