<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DataParserService
{
    private array $cityMapping = [];

    private array $buildTime = [];

    /**
     * 解析 ZIP 格式的租賃資料
     */
    public function parseZipData(string $filePath): array
    {
        try {
            $zipPath = Storage::path($filePath);
            $extractPath = storage_path('app/temp/extracted');

            // 確保解壓目錄存在
            if (! is_dir($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            // 清空解壓目錄
            $files = glob($extractPath.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $zip = new ZipArchive;
            $result = $zip->open($zipPath);

            if ($result !== true) {
                throw new \Exception("無法開啟 ZIP 檔案: $result");
            }

            // 解壓縮到臨時目錄
            $zip->extractTo($extractPath);
            $zip->close();

            Log::info('ZIP 檔案解壓縮成功', [
                'file_path' => $filePath,
                'extract_path' => $extractPath,
                'files_count' => count(glob($extractPath.'/*')),
            ]);

            // 解析 manifest.csv
            $manifestPath = $extractPath.'/manifest.csv';
            if (file_exists($manifestPath)) {
                $this->parseManifest($manifestPath);
            }

            // 解析 build_time.xml
            $buildTimePath = $extractPath.'/build_time.xml';
            if (file_exists($buildTimePath)) {
                $this->parseBuildTime($buildTimePath);
            }

            // 找出主要的租賃資料檔案 (*_lvr_land_c.csv)
            $csvFiles = glob($extractPath.'/*_lvr_land_c.csv');

            $allData = [];
            $totalProcessed = 0;
            $totalErrors = 0;

            foreach ($csvFiles as $csvFile) {
                $fileName = basename($csvFile);
                Log::info('開始處理 CSV 檔案', ['file' => $fileName]);

                // 從檔案名解析縣市代碼
                $prefix = substr($fileName, 0, 1);
                $city = $this->cityMapping[$prefix] ?? '未知縣市';

                $result = $this->parseMainRentFile($csvFile, $city);
                $allData = array_merge($allData, $result['data']);
                $totalProcessed += $result['processed_count'];
                $totalErrors += $result['error_count'];

                Log::info('CSV 檔案處理完成', [
                    'file' => $fileName,
                    'city' => $city,
                    'processed' => $result['processed_count'],
                    'errors' => $result['error_count'],
                ]);
            }

            // 清理臨時檔案
            foreach (glob($extractPath.'/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            Log::info('ZIP 解析完成', [
                'csv_files_count' => count($csvFiles),
                'total_processed' => $totalProcessed,
                'total_errors' => $totalErrors,
                'success_rate' => $totalProcessed > 0 ? round(($totalProcessed / ($totalProcessed + $totalErrors)) * 100, 2) : 0,
            ]);

            return [
                'success' => true,
                'data' => $allData,
                'processed_count' => $totalProcessed,
                'error_count' => $totalErrors,
                'csv_files_count' => count($csvFiles),
                'city_mapping' => $this->cityMapping,
                'build_time' => $this->buildTime,
            ];

        } catch (\Exception $e) {
            Log::error('ZIP 解析失敗', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * 解析 manifest.csv 建立縣市對應表
     */
    private function parseManifest(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $name = $row[0];
            $description = $row[2];

            // 解析縣市名稱
            if (preg_match('/(.+?)(市|縣)/', $description, $matches)) {
                $city = $matches[1].$matches[2];
                $prefix = substr($name, 0, 1);
                $this->cityMapping[$prefix] = $city;
            }
        }
        fclose($handle);

        Log::info('縣市對應表建立完成', ['mapping' => $this->cityMapping]);
    }

    /**
     * 解析 build_time.xml 獲取時間範圍
     */
    private function parseBuildTime(string $filePath): void
    {
        $xml = simplexml_load_file($filePath);
        $timeText = (string) $xml->lvr_time;

        // 解析租賃案件時間範圍
        if (preg_match('/訂約日期\s*(\d+)年(\d+)月(\d+)日至\s*(\d+)年(\d+)月(\d+)日/', $timeText, $matches)) {
            $this->buildTime = [
                'start_year' => (int) $matches[1],
                'start_month' => (int) $matches[2],
                'start_day' => (int) $matches[3],
                'end_year' => (int) $matches[4],
                'end_month' => (int) $matches[5],
                'end_day' => (int) $matches[6],
            ];
        }

        Log::info('時間範圍解析完成', ['build_time' => $this->buildTime]);
    }

    /**
     * 解析主表檔案
     */
    private function parseMainRentFile(string $filePath, string $city): array
    {
        $data = [];
        $processedCount = 0;
        $errorCount = 0;

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');

        // 處理BOM字符問題
        $header = array_map(function ($col) {
            return trim($col, "\xEF\xBB\xBF");
        }, $header);

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            // 跳過英文標題行
            if (isset($row[0]) && strpos($row[0], 'The ') === 0) {
                continue;
            }

            // 檢查是否為有效資料行
            if (count($row) >= count($header)) {
                try {
                    $record = array_combine($header, $row);
                    $normalizedRecord = $this->normalizeRentalRecord($record, $city);
                    $data[] = $normalizedRecord;
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::warning('解析記錄時發生錯誤', [
                        'error' => $e->getMessage(),
                        'record' => $record ?? null,
                    ]);
                }
            }
        }
        fclose($handle);

        return [
            'data' => $data,
            'processed_count' => $processedCount,
            'error_count' => $errorCount,
        ];
    }

    /**
     * 標準化租賃記錄資料
     */
    private function normalizeRentalRecord(array $record, string $city): array
    {
        // 基本資訊
        $serialNumber = $record['編號'] ?? '';
        $district = $record['鄉鎮市區'] ?? '';
        $fullAddress = $record['土地位置建物門牌'] ?? '';

        // 租賃資訊
        $rentalType = $record['出租型態'] ?? '';
        $totalRent = $this->parsePrice($record['總額元'] ?? '0');
        $rentDate = $this->parseDate($record['租賃年月日'] ?? '');
        $rentalPeriod = $record['租賃期間'] ?? '';

        // 建物資訊
        $buildingType = $record['建物型態'] ?? '';
        $areaSqm = $this->parseArea($record['建物總面積平方公尺'] ?? '0');
        $areaPing = $this->convertToPing($areaSqm);
        $totalFloors = $this->parseInteger($record['總樓層數'] ?? '');
        $mainUse = $record['主要用途'] ?? '';
        $mainBuildingMaterials = $record['主要建材'] ?? '';
        $constructionYear = $this->parseConstructionYear($record['建築完成年月'] ?? '');

        // 格局資訊
        $bedrooms = (int) ($record['建物現況格局-房'] ?? 0);
        $livingRooms = (int) ($record['建物現況格局-廳'] ?? 0);
        $bathrooms = (int) ($record['建物現況格局-衛'] ?? 0);
        $compartmentPattern = "{$bedrooms}房{$livingRooms}廳{$bathrooms}衛";

        // 設施資訊
        $hasElevator = $this->parseYesNo($record['有無電梯'] ?? '');
        $hasManagement = $this->parseYesNo($record['有無管理組織'] ?? '');
        $hasFurniture = $this->parseYesNo($record['有無附傢俱'] ?? '');
        $equipment = $record['附屬設備'] ?? '';

        // 計算每坪租金
        $rentPerPing = $areaPing > 0 ? round($totalRent / $areaPing) : 0;

        // 計算建物年齡
        $buildingAge = $constructionYear ? date('Y') - $constructionYear : null;

        return [
            'serial_number' => $serialNumber,
            'city' => $city,
            'district' => $district,
            'latitude' => null,  // 預留給地理編碼
            'longitude' => null, // 預留給地理編碼
            'is_geocoded' => false,
            'rental_type' => $rentalType,
            'total_rent' => $totalRent,
            'rent_per_ping' => $rentPerPing,
            'rent_date' => $rentDate,
            'building_type' => $buildingType,
            'area_ping' => $areaPing,
            'building_age' => $buildingAge,
            'bedrooms' => $bedrooms,
            'living_rooms' => $livingRooms,
            'bathrooms' => $bathrooms,
            'has_elevator' => $hasElevator,
            'has_management_organization' => $hasManagement,
            'has_furniture' => $hasFurniture,
        ];
    }

    /**
     * 解析價格資料
     */
    private function parsePrice(string $price): float
    {
        $cleaned = preg_replace('/[^\d.]/', '', $price);

        return (float) $cleaned;
    }

    /**
     * 解析面積資料
     */
    private function parseArea(string $area): float
    {
        $cleaned = preg_replace('/[^\d.]/', '', $area);

        return (float) $cleaned;
    }

    /**
     * 轉換平方公尺為坪數
     */
    private function convertToPing(float $squareMeters): float
    {
        return round($squareMeters / 3.30579, 2);
    }

    /**
     * 解析整數
     */
    private function parseInteger(string $value): ?int
    {
        $cleaned = preg_replace('/[^\d]/', '', $value);

        return $cleaned ? (int) $cleaned : null;
    }

    /**
     * 解析日期資料
     */
    private function parseDate(string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            // 政府資料格式通常是 1140801 (民國年月日)
            if (preg_match('/^(\d{7})$/', $date)) {
                $year = substr($date, 0, 3) + 1911; // 民國年轉西元年
                $month = substr($date, 3, 2);
                $day = substr($date, 5, 2);

                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 解析是否格式
     */
    private function parseYesNo(string $value): bool
    {
        return in_array(trim($value), ['有', '是', 'yes', 'true', '1']);
    }

    /**
     * 解析建築完成年月
     */
    private function parseConstructionYear(string $yearMonth): ?int
    {
        if (empty($yearMonth)) {
            return null;
        }

        try {
            // 格式通常是 0730629 (民國年月)
            if (preg_match('/^(\d{6,7})$/', $yearMonth)) {
                $year = substr($yearMonth, 0, 3) + 1911; // 民國年轉西元年

                return (int) $year;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 將解析的資料儲存到資料庫
     */
    public function saveToDatabase(array $data): array
    {
        $savedCount = 0;
        $errorCount = 0;
        $errors = [];

        Log::info('開始儲存資料到資料庫', [
            'total_records' => count($data),
        ]);

        // 分批處理，每批 100 筆
        $batchSize = 100;
        $batches = array_chunk($data, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            try {
                // 使用事務處理整批資料
                DB::transaction(function () use ($batch, &$savedCount, &$errorCount, &$errors) {
                    foreach ($batch as $index => $record) {
                        try {
                            // 檢查是否已存在相同記錄（基於縣市、行政區、租金和日期）
                            $existing = Property::where('city', $record['city'])
                                ->where('district', $record['district'])
                                ->where('total_rent', $record['total_rent'])
                                ->where('rent_date', $record['rent_date'])
                                ->where('building_type', $record['building_type'])
                                ->first();

                            if ($existing) {
                                // 更新現有記錄
                                $existing->update($record);
                            } else {
                                // 建立新記錄
                                Property::create($record);
                            }

                            $savedCount++;
                        } catch (\Exception $e) {
                            $errorCount++;
                            $errors[] = [
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'record' => $record,
                            ];

                            Log::error('儲存記錄時發生錯誤', [
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'record' => $record,
                            ]);
                        }
                    }
                });

                // 批次間暫停，避免 I/O 過載
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 暫停 0.1 秒
                }

            } catch (\Exception $e) {
                Log::error('批次處理失敗', [
                    'batch_index' => $batchIndex,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('資料庫儲存完成', [
            'saved_count' => $savedCount,
            'error_count' => $errorCount,
            'success_rate' => $savedCount > 0 ? round(($savedCount / ($savedCount + $errorCount)) * 100, 2) : 0,
        ]);

        return [
            'success' => true,
            'saved_count' => $savedCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];
    }

    /**
     * 清理 UTF-8 字符
     */
    private function cleanUtf8Data($data): array|string
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$this->cleanUtf8String($key)] = $this->cleanUtf8String($value);
            }

            return $cleaned;
        }

        return $this->cleanUtf8String($data);
    }

    /**
     * 清理 UTF-8 字符串
     */
    private function cleanUtf8String($string): string
    {
        if (! is_string($string)) {
            return $string;
        }

        // 嘗試從 Big-5 轉換到 UTF-8
        if (mb_check_encoding($string, 'Big5')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'Big5');
        } elseif (mb_check_encoding($string, 'CP950')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'CP950');
        } else {
            // 嘗試自動檢測編碼
            $detected = mb_detect_encoding($string, ['Big5', 'CP950', 'UTF-8', 'ISO-8859-1'], true);
            if ($detected && $detected !== 'UTF-8') {
                $string = mb_convert_encoding($string, 'UTF-8', $detected);
            }
        }

        // 移除控制字符
        $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);

        // 確保字符串是有效的 UTF-8
        if (! mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'auto');
        }

        return $string;
    }
}
