<?php

namespace App\Services;

use App\Models\Property;
use App\Models\RiskAssessment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RiskAssessmentService
{
    private const RISK_CACHE_TTL = 7200; // 2小時

    public function assessInvestmentRisk(array $propertyData): array
    {
        try {
            // 1. 市場風險評估
            $marketRisk = $this->assessMarketRisk($propertyData);

            // 2. 區域風險評估
            $locationRisk = $this->assessLocationRisk($propertyData);

            // 3. 財務風險評估
            $financialRisk = $this->assessFinancialRisk($propertyData);

            // 4. 綜合風險評分
            $overallRisk = $this->calculateOverallRisk($marketRisk, $locationRisk, $financialRisk);

            // 5. 儲存風險評估
            if (isset($propertyData['property_id'])) {
                $this->saveRiskAssessment($propertyData['property_id'], $overallRisk);
            }

            return [
                'status' => 'success',
                'overall_risk_score' => $overallRisk['score'],
                'risk_level' => $overallRisk['level'],
                'market_risk' => $marketRisk,
                'location_risk' => $locationRisk,
                'financial_risk' => $financialRisk,
                'recommendations' => $overallRisk['recommendations'],
                'assessed_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Risk assessment failed', [
                'error' => $e->getMessage(),
                'property_data' => $propertyData,
            ]);

            return [
                'status' => 'error',
                'message' => 'Risk assessment failed: '.$e->getMessage(),
            ];
        }
    }

    public function getRiskTrends(?string $district = null): array
    {
        $cacheKey = 'risk_trends_'.($district ?? 'all');

        return Cache::remember($cacheKey, self::RISK_CACHE_TTL, function () use ($district) {
            return $this->analyzeRiskTrends($district);
        });
    }

    private function assessMarketRisk(array $propertyData): array
    {
        $district = $propertyData['district'] ?? '';
        $rentPrice = $propertyData['rent_per_month'] ?? 0;

        // 計算價格波動率
        $priceVolatility = $this->calculatePriceVolatility($district, $rentPrice);

        // 分析市場趨勢
        $marketTrend = $this->analyzeMarketTrend($district);

        // 評估競爭程度
        $competitionLevel = $this->assessCompetitionLevel($district);

        return [
            'price_volatility' => $priceVolatility,
            'market_trend' => $marketTrend,
            'competition_level' => $competitionLevel,
            'score' => $this->calculateMarketRiskScore($priceVolatility, $marketTrend, $competitionLevel),
        ];
    }

    private function assessLocationRisk(array $propertyData): array
    {
        $district = $propertyData['district'] ?? '';
        $latitude = $propertyData['latitude'] ?? 0;
        $longitude = $propertyData['longitude'] ?? 0;

        // 交通便利性評估
        $transportAccess = $this->assessTransportAccess($district, $latitude, $longitude);

        // 生活機能評估
        $facilityAvailability = $this->assessFacilityAvailability($district);

        // 安全評分
        $safetyScore = $this->calculateSafetyScore($district);

        return [
            'transport_accessibility' => $transportAccess,
            'facility_availability' => $facilityAvailability,
            'safety_score' => $safetyScore,
            'score' => $this->calculateLocationRiskScore($transportAccess, $facilityAvailability, $safetyScore),
        ];
    }

    private function assessFinancialRisk(array $propertyData): array
    {
        $rentPrice = $propertyData['rent_per_month'] ?? 0;
        $area = $propertyData['total_floor_area'] ?? 0;

        // 計算租金收益率
        $rentalYield = $this->calculateRentalYield($rentPrice, $area);

        // 計算價格收入比
        $priceToIncomeRatio = $this->calculatePriceToIncomeRatio($rentPrice);

        // 計算負擔能力指數
        $affordabilityIndex = $this->calculateAffordabilityIndex($rentPrice, $area);

        return [
            'rental_yield' => $rentalYield,
            'price_to_income_ratio' => $priceToIncomeRatio,
            'affordability_index' => $affordabilityIndex,
            'score' => $this->calculateFinancialRiskScore($rentalYield, $priceToIncomeRatio, $affordabilityIndex),
        ];
    }

    private function calculateOverallRisk(array $marketRisk, array $locationRisk, array $financialRisk): array
    {
        $marketScore = $marketRisk['score'];
        $locationScore = $locationRisk['score'];
        $financialScore = $financialRisk['score'];

        // 加權平均計算總體風險分數
        $overallScore = ($marketScore * 0.4) + ($locationScore * 0.35) + ($financialScore * 0.25);

        $riskLevel = $this->determineRiskLevel($overallScore);
        $recommendations = $this->generateRiskRecommendations($marketScore, $locationScore, $financialScore);

        return [
            'score' => round($overallScore, 2),
            'level' => $riskLevel,
            'recommendations' => $recommendations,
        ];
    }

    private function calculatePriceVolatility(string $district, float $rentPrice): float
    {
        $properties = Property::where('district', $district)
            ->whereNotNull('rent_per_month')
            ->where('rent_per_month', '>', 0)
            ->get();

        if ($properties->count() < 5) {
            return 0.5; // 預設中等波動率
        }

        $prices = $properties->pluck('rent_per_month')->toArray();
        $mean = array_sum($prices) / count($prices);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $prices)) / count($prices);

        $stdDev = sqrt($variance);
        $coefficientOfVariation = $stdDev / $mean;

        return min(1.0, $coefficientOfVariation);
    }

    private function analyzeMarketTrend(string $district): string
    {
        $properties = Property::where('district', $district)
            ->whereNotNull('rent_per_month')
            ->whereNotNull('rent_date')
            ->orderBy('rent_date', 'desc')
            ->limit(20)
            ->get();

        if ($properties->count() < 10) {
            return 'stable';
        }

        $recentProperties = $properties->take(10);
        $olderProperties = $properties->skip(10);

        $recentAvg = $recentProperties->avg('rent_per_month');
        $olderAvg = $olderProperties->avg('rent_per_month');

        $changePercent = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;

        if ($changePercent > 10) {
            return 'rising';
        } elseif ($changePercent < -10) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    private function assessCompetitionLevel(string $district): float
    {
        $propertyCount = Property::where('district', $district)->count();

        // 基於物件數量評估競爭程度
        if ($propertyCount > 100) {
            return 0.8; // 高競爭
        } elseif ($propertyCount > 50) {
            return 0.6; // 中等競爭
        } elseif ($propertyCount > 20) {
            return 0.4; // 低競爭
        } else {
            return 0.2; // 很低競爭
        }
    }

    private function assessTransportAccess(string $district, float $latitude, float $longitude): float
    {
        // 簡化的交通便利性評估
        $metroStations = [
            '中正區' => 0.9, '大同區' => 0.8, '中山區' => 0.9, '松山區' => 0.7,
            '大安區' => 0.9, '萬華區' => 0.6, '信義區' => 0.8, '士林區' => 0.7,
            '北投區' => 0.6, '內湖區' => 0.7, '南港區' => 0.8, '文山區' => 0.5,
        ];

        return $metroStations[$district] ?? 0.5;
    }

    private function assessFacilityAvailability(string $district): float
    {
        // 簡化的生活機能評估
        $facilityScores = [
            '中正區' => 0.9, '大同區' => 0.8, '中山區' => 0.9, '松山區' => 0.8,
            '大安區' => 0.9, '萬華區' => 0.7, '信義區' => 0.9, '士林區' => 0.8,
            '北投區' => 0.7, '內湖區' => 0.8, '南港區' => 0.7, '文山區' => 0.6,
        ];

        return $facilityScores[$district] ?? 0.5;
    }

    private function calculateSafetyScore(string $district): float
    {
        // 簡化的安全評分
        $safetyScores = [
            '中正區' => 0.8, '大同區' => 0.7, '中山區' => 0.8, '松山區' => 0.8,
            '大安區' => 0.9, '萬華區' => 0.6, '信義區' => 0.9, '士林區' => 0.8,
            '北投區' => 0.8, '內湖區' => 0.8, '南港區' => 0.7, '文山區' => 0.8,
        ];

        return $safetyScores[$district] ?? 0.5;
    }

    private function calculateRentalYield(float $rentPrice, float $area): float
    {
        // 簡化的租金收益率計算
        $estimatedValue = $area * 800000; // 假設每坪80萬
        $annualRent = $rentPrice * 12;

        return $estimatedValue > 0 ? $annualRent / $estimatedValue : 0;
    }

    private function calculatePriceToIncomeRatio(float $rentPrice): float
    {
        // 假設平均月收入為5萬元
        $averageIncome = 50000;

        return $rentPrice / $averageIncome;
    }

    private function calculateAffordabilityIndex(float $rentPrice, float $area): float
    {
        $pricePerSqm = $area > 0 ? $rentPrice / $area : 0;
        $averageIncome = 50000;

        // 負擔能力指數 = 平均收入 / 每坪租金
        return $averageIncome / max($pricePerSqm, 1);
    }

    private function calculateMarketRiskScore(float $volatility, string $trend, float $competition): float
    {
        $score = 0;

        // 波動率影響 (40%)
        $score += $volatility * 40;

        // 趨勢影響 (35%)
        switch ($trend) {
            case 'declining':
                $score += 30;
                break;
            case 'stable':
                $score += 20;
                break;
            case 'rising':
                $score += 10;
                break;
        }

        // 競爭程度影響 (25%)
        $score += $competition * 25;

        return min(100, $score);
    }

    private function calculateLocationRiskScore(float $transport, float $facilities, float $safety): float
    {
        // 交通便利性 (40%) + 生活機能 (35%) + 安全性 (25%)
        $score = ($transport * 40) + ($facilities * 35) + ($safety * 25);

        // 轉換為風險分數 (分數越高風險越低)
        return 100 - $score;
    }

    private function calculateFinancialRiskScore(float $yield, float $priceRatio, float $affordability): float
    {
        $score = 0;

        // 租金收益率影響 (40%)
        if ($yield < 0.03) {
            $score += 40;
        } elseif ($yield < 0.05) {
            $score += 20;
        } else {
            $score += 10;
        }

        // 價格收入比影響 (35%)
        if ($priceRatio > 0.7) {
            $score += 35;
        } elseif ($priceRatio > 0.5) {
            $score += 20;
        } else {
            $score += 10;
        }

        // 負擔能力影響 (25%)
        if ($affordability < 50) {
            $score += 25;
        } elseif ($affordability < 100) {
            $score += 15;
        } else {
            $score += 5;
        }

        return min(100, $score);
    }

    private function determineRiskLevel(float $score): string
    {
        if ($score >= 70) {
            return '高風險';
        } elseif ($score >= 40) {
            return '中風險';
        } else {
            return '低風險';
        }
    }

    private function generateRiskRecommendations(float $marketScore, float $locationScore, float $financialScore): array
    {
        $recommendations = [];

        if ($marketScore > 60) {
            $recommendations[] = '市場風險較高，建議謹慎投資並密切關注市場動態';
        }

        if ($locationScore > 60) {
            $recommendations[] = '區域風險較高，建議考慮其他交通便利或生活機能更好的區域';
        }

        if ($financialScore > 60) {
            $recommendations[] = '財務風險較高，建議評估租金收益率和負擔能力';
        }

        if (empty($recommendations)) {
            $recommendations[] = '整體風險較低，適合投資';
        }

        return $recommendations;
    }

    private function analyzeRiskTrends(?string $district = null): array
    {
        // 風險趨勢分析
        return [
            'trend' => 'stable',
            'average_risk' => 45,
            'risk_change' => 2,
            'district' => $district ?? 'all',
        ];
    }

    private function saveRiskAssessment(int $propertyId, array $riskData): void
    {
        try {
            RiskAssessment::create([
                'property_id' => $propertyId,
                'risk_level' => $riskData['level'],
                'risk_score' => $riskData['score'],
                'factors' => [
                    'market_risk' => $riskData['score'],
                    'location_risk' => $riskData['score'],
                    'financial_risk' => $riskData['score'],
                ],
                'suggestions' => $riskData['recommendations'],
                'metadata' => [
                    'assessed_at' => now()->toISOString(),
                    'assessment_version' => '1.0.0',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save risk assessment', [
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
