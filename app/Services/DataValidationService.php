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
            'statistics' => [],
        ];

        Log::info('開始驗證租賃資料', [
            'total_records' => count($data),
        ]);

        foreach ($data as $index => $record) {
            $recordValidation = $this->validateRecord($record, $index);

            if ($recordValidation['is_valid']) {
                $validationResults['valid_records']++;
            } else {
                $validationResults['invalid_records']++;
                $validationResults['errors'] = array_merge($validationResults['errors'], $recordValidation['errors']);
            }

            if (! empty($recordValidation['warnings'])) {
                $validationResults['warnings'] = array_merge($validationResults['warnings'], $recordValidation['warnings']);
            }
        }

        // 計算統計資料
        $validationResults['statistics'] = $this->calculateStatistics($data);
        $validationResults['success_rate'] = $validationResults['total_records'] > 0
            ? round(($validationResults['valid_records'] / $validationResults['total_records']) * 100, 2)
            : 0;

        Log::info('資料驗證完成', [
            'valid_records' => $validationResults['valid_records'],
            'invalid_records' => $validationResults['invalid_records'],
            'success_rate' => $validationResults['success_rate'],
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

        // 必要欄位檢查 - 使用新的資料結構
        $requiredFields = ['full_address', 'district', 'total_rent', 'rent_per_ping', 'area_ping'];
        foreach ($requiredFields as $field) {
            if (empty($record[$field])) {
                $errors[] = "記錄 {$index}: 缺少必要欄位 '{$field}'";
                $isValid = false;
            }
        }

        // 地址驗證
        if (! empty($record['full_address'])) {
            if (strlen($record['full_address']) < 5) {
                $warnings[] = "記錄 {$index}: 地址過短，可能不完整";
            }

            if (! preg_match('/[\x{4e00}-\x{9fff}]/u', $record['full_address'])) {
                $warnings[] = "記錄 {$index}: 地址不包含中文字符，可能格式錯誤";
            }
        }

        // 租金驗證
        if (! empty($record['total_rent'])) {
            $totalRent = (float) $record['total_rent'];
            if ($totalRent <= 0) {
                $errors[] = "記錄 {$index}: 總租金必須大於 0";
                $isValid = false;
            } elseif ($totalRent < 1000) {
                $warnings[] = "記錄 {$index}: 總租金過低 ({$totalRent})，可能資料錯誤";
            } elseif ($totalRent > 100000000) {
                $warnings[] = "記錄 {$index}: 總租金過高 ({$totalRent})，可能資料錯誤";
            }
        }

        if (! empty($record['rent_per_ping'])) {
            $rentPerPing = (float) $record['rent_per_ping'];
            if ($rentPerPing <= 0) {
                $errors[] = "記錄 {$index}: 每坪租金必須大於 0";
                $isValid = false;
            } elseif ($rentPerPing < 100) {
                $warnings[] = "記錄 {$index}: 每坪租金過低 ({$rentPerPing})，可能資料錯誤";
            } elseif ($rentPerPing > 100000) {
                $warnings[] = "記錄 {$index}: 每坪租金過高 ({$rentPerPing})，可能資料錯誤";
            }
        }

        // 面積驗證
        if (! empty($record['area_ping'])) {
            $areaPing = (float) $record['area_ping'];
            if ($areaPing <= 0) {
                $errors[] = "記錄 {$index}: 面積必須大於 0";
                $isValid = false;
            } elseif ($areaPing < 1) {
                $warnings[] = "記錄 {$index}: 面積過小 ({$areaPing}坪)，可能資料錯誤";
            } elseif ($areaPing > 1000) {
                $warnings[] = "記錄 {$index}: 面積過大 ({$areaPing}坪)，可能資料錯誤";
            }
        }

        // 房間數驗證
        if (! empty($record['bedrooms']) && $record['bedrooms'] < 0) {
            $errors[] = "記錄 {$index}: 房間數不能為負數";
            $isValid = false;
        }
        if (! empty($record['bedrooms']) && $record['bedrooms'] > 20) {
            $warnings[] = "記錄 {$index}: 房間數過多 ({$record['bedrooms']})，可能資料錯誤";
        }

        // 日期驗證
        if (! empty($record['rent_date'])) {
            $date = $record['rent_date'];
            if (! $this->isValidDate($date)) {
                $errors[] = "記錄 {$index}: 租賃日期格式錯誤 ({$date})";
                $isValid = false;
            } else {
                $rentDate = \DateTime::createFromFormat('Y-m-d', $date);
                $now = new \DateTime;
                $diff = $now->diff($rentDate);

                if ($diff->days > 365 * 10) { // 超過10年
                    $warnings[] = "記錄 {$index}: 租賃日期過舊 ({$date})";
                } elseif ($rentDate > $now) {
                    $warnings[] = "記錄 {$index}: 租賃日期為未來日期 ({$date})";
                }
            }
        }

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
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
            'rent_range' => ['min' => null, 'max' => null, 'avg' => null],
            'rent_per_ping_range' => ['min' => null, 'max' => null, 'avg' => null],
            'area_ping_range' => ['min' => null, 'max' => null, 'avg' => null],
            'city_distribution' => [],
            'district_distribution' => [],
            'building_type_distribution' => [],
            'rental_type_distribution' => [],
            'date_range' => ['earliest' => null, 'latest' => null],
        ];

        $rents = [];
        $rentPerPings = [];
        $areaPings = [];
        $cities = [];
        $districts = [];
        $buildingTypes = [];
        $rentalTypes = [];
        $dates = [];

        foreach ($data as $record) {
            // 租金統計
            if (! empty($record['total_rent'])) {
                $rents[] = (float) $record['total_rent'];
            }

            if (! empty($record['rent_per_ping'])) {
                $rentPerPings[] = (float) $record['rent_per_ping'];
            }

            if (! empty($record['area_ping'])) {
                $areaPings[] = (float) $record['area_ping'];
            }

            // 縣市分布
            if (! empty($record['city'])) {
                $cities[$record['city']] = ($cities[$record['city']] ?? 0) + 1;
            }

            // 行政區分布
            if (! empty($record['district'])) {
                $districts[$record['district']] = ($districts[$record['district']] ?? 0) + 1;
            }

            // 建物型態分布
            if (! empty($record['building_type'])) {
                $buildingTypes[$record['building_type']] = ($buildingTypes[$record['building_type']] ?? 0) + 1;
            }

            // 租賃類型分布
            if (! empty($record['rental_type'])) {
                $rentalTypes[$record['rental_type']] = ($rentalTypes[$record['rental_type']] ?? 0) + 1;
            }

            // 日期範圍
            if (! empty($record['rent_date'])) {
                $dates[] = $record['rent_date'];
            }
        }

        // 租金統計
        if (! empty($rents)) {
            $stats['rent_range'] = [
                'min' => min($rents),
                'max' => max($rents),
                'avg' => round(array_sum($rents) / count($rents), 2),
            ];
        }

        // 每坪租金統計
        if (! empty($rentPerPings)) {
            $stats['rent_per_ping_range'] = [
                'min' => min($rentPerPings),
                'max' => max($rentPerPings),
                'avg' => round(array_sum($rentPerPings) / count($rentPerPings), 2),
            ];
        }

        // 面積統計
        if (! empty($areaPings)) {
            $stats['area_ping_range'] = [
                'min' => min($areaPings),
                'max' => max($areaPings),
                'avg' => round(array_sum($areaPings) / count($areaPings), 2),
            ];
        }

        // 縣市分布
        $stats['city_distribution'] = $cities;

        // 行政區分布
        $stats['district_distribution'] = $districts;

        // 建物型態分布
        $stats['building_type_distribution'] = $buildingTypes;

        // 租賃類型分布
        $stats['rental_type_distribution'] = $rentalTypes;

        // 日期範圍
        if (! empty($dates)) {
            sort($dates);
            $stats['date_range'] = [
                'earliest' => $dates[0],
                'latest' => end($dates),
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
            'recommendations' => [],
        ];

        $totalRecords = count($data);
        if ($totalRecords === 0) {
            return $qualityReport;
        }

        // 完整性檢查
        $completeRecords = 0;
        $requiredFields = ['full_address', 'district', 'total_rent', 'rent_per_ping', 'area_ping'];

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

        // 準確性檢查 (基於租金合理性)
        $accurateRecords = 0;
        foreach ($data as $record) {
            $isAccurate = true;

            if (! empty($record['total_rent']) && ! empty($record['rent_per_ping']) && ! empty($record['area_ping'])) {
                $calculatedRentPerPing = $record['total_rent'] / $record['area_ping'];
                $actualRentPerPing = $record['rent_per_ping'];
                $difference = abs($calculatedRentPerPing - $actualRentPerPing) / $actualRentPerPing;

                if ($difference > 0.1) { // 差異超過10%
                    $isAccurate = false;
                }
            }

            if ($isAccurate) {
                $accurateRecords++;
            }
        }

        $qualityReport['accuracy_score'] = round(($accurateRecords / $totalRecords) * 100, 2);

        // 一致性檢查 (基於縣市分布)
        $cityCount = count(array_unique(array_column($data, 'city')));
        $expectedCities = 6; // 主要縣市數量
        $consistencyScore = min(100, ($cityCount / $expectedCities) * 100);
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
            $qualityReport['recommendations'][] = '資料準確性不足，建議檢查租金計算';
        }

        if ($qualityReport['consistency_score'] < 80) {
            $qualityReport['recommendations'][] = '資料一致性不足，建議檢查縣市分布';
        }

        return $qualityReport;
    }
}
