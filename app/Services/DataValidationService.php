<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DataValidationService
{
    /**
     * 驗證租賃資料的完整性
     */
    public function validateRentalData(array $data): array
    {
        $validationResults = [
            'total_records' => count($data),
            'valid_records' => 0,
            'invalid_records' => 0,
            'warnings' => [],
            'errors' => [],
            'statistics' => []
        ];

        Log::info("開始驗證租賃資料", [
            'total_records' => count($data)
        ]);

        foreach ($data as $index => $record) {
            $recordValidation = $this->validateRecord($record, $index);
            
            if ($recordValidation['is_valid']) {
                $validationResults['valid_records']++;
            } else {
                $validationResults['invalid_records']++;
                $validationResults['errors'] = array_merge($validationResults['errors'], $recordValidation['errors']);
            }
            
            if (!empty($recordValidation['warnings'])) {
                $validationResults['warnings'] = array_merge($validationResults['warnings'], $recordValidation['warnings']);
            }
        }

        // 計算統計資料
        $validationResults['statistics'] = $this->calculateStatistics($data);
        $validationResults['success_rate'] = $validationResults['total_records'] > 0 
            ? round(($validationResults['valid_records'] / $validationResults['total_records']) * 100, 2) 
            : 0;

        Log::info("資料驗證完成", [
            'valid_records' => $validationResults['valid_records'],
            'invalid_records' => $validationResults['invalid_records'],
            'success_rate' => $validationResults['success_rate']
        ]);

        return $validationResults;
    }

    /**
     * 驗證單筆記錄
     */
    private function validateRecord(array $record, int $index): array
    {
        $errors = [];
        $warnings = [];
        $isValid = true;

        // 必要欄位檢查
        $requiredFields = ['address', 'district', 'total_price', 'unit_price', 'area'];
        foreach ($requiredFields as $field) {
            if (empty($record[$field])) {
                $errors[] = "記錄 {$index}: 缺少必要欄位 '{$field}'";
                $isValid = false;
            }
        }

        // 地址驗證
        if (!empty($record['address'])) {
            if (strlen($record['address']) < 5) {
                $warnings[] = "記錄 {$index}: 地址過短，可能不完整";
            }
            
            if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $record['address'])) {
                $warnings[] = "記錄 {$index}: 地址不包含中文字符，可能格式錯誤";
            }
        }

        // 價格驗證
        if (!empty($record['total_price'])) {
            $totalPrice = (float) $record['total_price'];
            if ($totalPrice <= 0) {
                $errors[] = "記錄 {$index}: 總價必須大於 0";
                $isValid = false;
            } elseif ($totalPrice < 1000) {
                $warnings[] = "記錄 {$index}: 總價過低 ({$totalPrice})，可能資料錯誤";
            } elseif ($totalPrice > 100000000) {
                $warnings[] = "記錄 {$index}: 總價過高 ({$totalPrice})，可能資料錯誤";
            }
        }

        if (!empty($record['unit_price'])) {
            $unitPrice = (float) $record['unit_price'];
            if ($unitPrice <= 0) {
                $errors[] = "記錄 {$index}: 單價必須大於 0";
                $isValid = false;
            } elseif ($unitPrice < 100) {
                $warnings[] = "記錄 {$index}: 單價過低 ({$unitPrice})，可能資料錯誤";
            } elseif ($unitPrice > 100000) {
                $warnings[] = "記錄 {$index}: 單價過高 ({$unitPrice})，可能資料錯誤";
            }
        }

        // 面積驗證
        if (!empty($record['area'])) {
            $area = (float) $record['area'];
            if ($area <= 0) {
                $errors[] = "記錄 {$index}: 面積必須大於 0";
                $isValid = false;
            } elseif ($area < 1) {
                $warnings[] = "記錄 {$index}: 面積過小 ({$area})，可能資料錯誤";
            } elseif ($area > 10000) {
                $warnings[] = "記錄 {$index}: 面積過大 ({$area})，可能資料錯誤";
            }
        }

        // 房間數驗證
        if (!empty($record['rooms']) && is_array($record['rooms'])) {
            $rooms = $record['rooms'];
            if (isset($rooms['bedrooms']) && $rooms['bedrooms'] < 0) {
                $errors[] = "記錄 {$index}: 房間數不能為負數";
                $isValid = false;
            }
            if (isset($rooms['bedrooms']) && $rooms['bedrooms'] > 20) {
                $warnings[] = "記錄 {$index}: 房間數過多 ({$rooms['bedrooms']})，可能資料錯誤";
            }
        }

        // 日期驗證
        if (!empty($record['transaction_date'])) {
            $date = $record['transaction_date'];
            if (!$this->isValidDate($date)) {
                $errors[] = "記錄 {$index}: 交易日期格式錯誤 ({$date})";
                $isValid = false;
            } else {
                $transactionDate = \DateTime::createFromFormat('Y-m-d', $date);
                $now = new \DateTime();
                $diff = $now->diff($transactionDate);
                
                if ($diff->days > 365 * 10) { // 超過10年
                    $warnings[] = "記錄 {$index}: 交易日期過舊 ({$date})";
                } elseif ($transactionDate > $now) {
                    $warnings[] = "記錄 {$index}: 交易日期為未來日期 ({$date})";
                }
            }
        }

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * 驗證日期格式
     */
    private function isValidDate(string $date): bool
    {
        $formats = ['Y-m-d', 'Y/m/d', 'Ymd'];
        
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false && $parsed->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 計算統計資料
     */
    private function calculateStatistics(array $data): array
    {
        $stats = [
            'price_range' => ['min' => null, 'max' => null, 'avg' => null],
            'unit_price_range' => ['min' => null, 'max' => null, 'avg' => null],
            'area_range' => ['min' => null, 'max' => null, 'avg' => null],
            'district_distribution' => [],
            'building_type_distribution' => [],
            'date_range' => ['earliest' => null, 'latest' => null]
        ];

        $prices = [];
        $unitPrices = [];
        $areas = [];
        $districts = [];
        $buildingTypes = [];
        $dates = [];

        foreach ($data as $record) {
            // 價格統計
            if (!empty($record['total_price'])) {
                $prices[] = (float) $record['total_price'];
            }
            
            if (!empty($record['unit_price'])) {
                $unitPrices[] = (float) $record['unit_price'];
            }
            
            if (!empty($record['area'])) {
                $areas[] = (float) $record['area'];
            }
            
            // 行政區分布
            if (!empty($record['district'])) {
                $districts[$record['district']] = ($districts[$record['district']] ?? 0) + 1;
            }
            
            // 建物型態分布
            if (!empty($record['building_type'])) {
                $buildingTypes[$record['building_type']] = ($buildingTypes[$record['building_type']] ?? 0) + 1;
            }
            
            // 日期範圍
            if (!empty($record['transaction_date'])) {
                $dates[] = $record['transaction_date'];
            }
        }

        // 價格統計
        if (!empty($prices)) {
            $stats['price_range'] = [
                'min' => min($prices),
                'max' => max($prices),
                'avg' => round(array_sum($prices) / count($prices), 2)
            ];
        }

        // 單價統計
        if (!empty($unitPrices)) {
            $stats['unit_price_range'] = [
                'min' => min($unitPrices),
                'max' => max($unitPrices),
                'avg' => round(array_sum($unitPrices) / count($unitPrices), 2)
            ];
        }

        // 面積統計
        if (!empty($areas)) {
            $stats['area_range'] = [
                'min' => min($areas),
                'max' => max($areas),
                'avg' => round(array_sum($areas) / count($areas), 2)
            ];
        }

        // 行政區分布
        $stats['district_distribution'] = $districts;

        // 建物型態分布
        $stats['building_type_distribution'] = $buildingTypes;

        // 日期範圍
        if (!empty($dates)) {
            sort($dates);
            $stats['date_range'] = [
                'earliest' => $dates[0],
                'latest' => end($dates)
            ];
        }

        return $stats;
    }

    /**
     * 檢查資料品質
     */
    public function checkDataQuality(array $data): array
    {
        $qualityReport = [
            'overall_score' => 0,
            'completeness_score' => 0,
            'accuracy_score' => 0,
            'consistency_score' => 0,
            'recommendations' => []
        ];

        $totalRecords = count($data);
        if ($totalRecords === 0) {
            return $qualityReport;
        }

        // 完整性檢查
        $completeRecords = 0;
        $requiredFields = ['address', 'district', 'total_price', 'unit_price', 'area'];
        
        foreach ($data as $record) {
            $isComplete = true;
            foreach ($requiredFields as $field) {
                if (empty($record[$field])) {
                    $isComplete = false;
                    break;
                }
            }
            if ($isComplete) {
                $completeRecords++;
            }
        }
        
        $qualityReport['completeness_score'] = round(($completeRecords / $totalRecords) * 100, 2);

        // 準確性檢查 (基於價格合理性)
        $accurateRecords = 0;
        foreach ($data as $record) {
            $isAccurate = true;
            
            if (!empty($record['total_price']) && !empty($record['unit_price']) && !empty($record['area'])) {
                $calculatedUnitPrice = $record['total_price'] / $record['area'];
                $actualUnitPrice = $record['unit_price'];
                $difference = abs($calculatedUnitPrice - $actualUnitPrice) / $actualUnitPrice;
                
                if ($difference > 0.1) { // 差異超過10%
                    $isAccurate = false;
                }
            }
            
            if ($isAccurate) {
                $accurateRecords++;
            }
        }
        
        $qualityReport['accuracy_score'] = round(($accurateRecords / $totalRecords) * 100, 2);

        // 一致性檢查 (基於行政區分布)
        $districtCount = count(array_unique(array_column($data, 'district')));
        $expectedDistricts = 12; // 台北市12個行政區
        $consistencyScore = min(100, ($districtCount / $expectedDistricts) * 100);
        $qualityReport['consistency_score'] = round($consistencyScore, 2);

        // 整體評分
        $qualityReport['overall_score'] = round(
            ($qualityReport['completeness_score'] + 
             $qualityReport['accuracy_score'] + 
             $qualityReport['consistency_score']) / 3, 2
        );

        // 建議
        if ($qualityReport['completeness_score'] < 80) {
            $qualityReport['recommendations'][] = '資料完整性不足，建議檢查必要欄位';
        }
        
        if ($qualityReport['accuracy_score'] < 80) {
            $qualityReport['recommendations'][] = '資料準確性不足，建議檢查價格計算';
        }
        
        if ($qualityReport['consistency_score'] < 80) {
            $qualityReport['recommendations'][] = '資料一致性不足，建議檢查行政區分布';
        }

        return $qualityReport;
    }
}
