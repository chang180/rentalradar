<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DataParserService
{
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

            // 找出主要的租賃資料檔案 (*_lvr_land_c.csv)
            $csvFiles = glob($extractPath.'/*_lvr_land_c.csv');

            $allData = [];
            $totalProcessed = 0;
            $totalErrors = 0;

            foreach ($csvFiles as $csvFile) {
                $fileName = basename($csvFile);
                Log::info('開始處理 CSV 檔案', ['file' => $fileName]);

                // 讀取檔案內容並處理 BOM
                $content = file_get_contents($csvFile);
                if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                    $content = substr($content, 3);
                }

                $lines = explode("\n", $content);
                if (count($lines) < 3) {
                    Log::warning('CSV 檔案行數不足', ['file' => $fileName, 'lines' => count($lines)]);

                    continue;
                }

                // 跳過中英文標題行，使用第一行（中文）作為標題
                $headers = str_getcsv($lines[0]);

                for ($i = 2; $i < count($lines); $i++) {
                    $line = trim($lines[$i]);
                    if (empty($line)) {
                        continue;
                    }

                    try {
                        $row = str_getcsv($line);
                        if (count($row) === count($headers)) {
                            $record = array_combine($headers, $row);
                            $allData[] = $this->normalizeRentalRecord($record);
                            $totalProcessed++;
                        } else {
                            $totalErrors++;
                            Log::warning('CSV 行資料欄位數量不符', [
                                'file' => $fileName,
                                'line' => $i + 1,
                                'expected' => count($headers),
                                'actual' => count($row),
                            ]);
                        }
                    } catch (\Exception $e) {
                        $totalErrors++;
                        Log::error('解析 CSV 行時發生錯誤', [
                            'file' => $fileName,
                            'line' => $i + 1,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info('CSV 檔案處理完成', [
                    'file' => $fileName,
                    'processed' => $totalProcessed,
                    'errors' => $totalErrors,
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
     * 解析 CSV 格式的租賃資料
     */
    public function parseCsvData(string $filePath): array
    {
        try {
            $content = Storage::get($filePath);
            $lines = explode("\n", $content);
            $headers = str_getcsv($lines[0]);
            $data = [];
            $processedCount = 0;
            $errorCount = 0;

            Log::info('開始解析 CSV 資料', [
                'file_path' => $filePath,
                'total_lines' => count($lines),
            ]);

            for ($i = 1; $i < count($lines); $i++) {
                if (empty(trim($lines[$i]))) {
                    continue;
                }

                try {
                    $row = str_getcsv($lines[$i]);
                    if (count($row) === count($headers)) {
                        $record = array_combine($headers, $row);
                        $data[] = $this->normalizeRentalRecord($record);
                        $processedCount++;
                    } else {
                        $errorCount++;
                        Log::warning('CSV 行資料欄位數量不符', [
                            'line' => $i + 1,
                            'expected' => count($headers),
                            'actual' => count($row),
                        ]);
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('解析 CSV 行時發生錯誤', [
                        'line' => $i + 1,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('CSV 解析完成', [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'success_rate' => $processedCount > 0 ? round(($processedCount / ($processedCount + $errorCount)) * 100, 2) : 0,
            ]);

            return [
                'success' => true,
                'data' => $data,
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'headers' => $headers,
            ];

        } catch (\Exception $e) {
            Log::error('CSV 解析失敗', [
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
     * 解析 XML 格式的租賃資料
     */
    public function parseXmlData(string $filePath): array
    {
        try {
            $content = Storage::get($filePath);
            $xml = simplexml_load_string($content);

            if ($xml === false) {
                throw new \Exception('無法解析 XML 內容');
            }

            Log::info('開始解析 XML 資料', [
                'file_path' => $filePath,
                'root_element' => $xml->getName(),
            ]);

            $data = [];
            $processedCount = 0;
            $errorCount = 0;

            // 根據 XML 結構解析資料
            foreach ($xml->children() as $record) {
                try {
                    $recordData = $this->xmlToArray($record);
                    $normalizedRecord = $this->normalizeRentalRecord($recordData);
                    $data[] = $normalizedRecord;
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::warning('解析 XML 記錄時發生錯誤', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('XML 解析完成', [
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
                'success_rate' => $processedCount > 0 ? round(($processedCount / ($processedCount + $errorCount)) * 100, 2) : 0,
            ]);

            return [
                'success' => true,
                'data' => $data,
                'processed_count' => $processedCount,
                'error_count' => $errorCount,
            ];

        } catch (\Exception $e) {
            Log::error('XML 解析失敗', [
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
     * 將 XML 節點轉換為陣列
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $array = [];

        foreach ($xml->children() as $child) {
            if ($child->count() > 0) {
                $array[$child->getName()] = $this->xmlToArray($child);
            } else {
                $array[$child->getName()] = (string) $child;
            }
        }

        return $array;
    }

    /**
     * 標準化租賃記錄資料
     */
    private function normalizeRentalRecord(array $record): array
    {
        return [
            'address' => $record['土地位置建物門牌'] ?? $record['address'] ?? '',
            'district' => $record['鄉鎮市區'] ?? $record['district'] ?? '',
            'road' => $record['路名'] ?? $record['road'] ?? '',
            'building_type' => $record['建物型態'] ?? $record['building_type'] ?? '',
            'total_price' => $this->parsePrice($record['總額元'] ?? $record['total_price'] ?? '0'),
            'unit_price' => $this->parsePrice($record['單價元平方公尺'] ?? $record['unit_price'] ?? '0'),
            'area' => $this->parseArea($record['建物總面積平方公尺'] ?? $record['area'] ?? '0'),
            'rooms' => $this->parseRoomsFromRecord($record),
            'floor' => $record['總樓層數'] ?? $record['floor'] ?? '',
            'elevator' => $record['有無電梯'] ?? $record['elevator'] ?? '',
            'purpose' => $record['主要用途'] ?? $record['purpose'] ?? '',
            'transaction_date' => $this->parseDate($record['交易年月日'] ?? $record['transaction_date'] ?? ''),
            'raw_data' => $record,
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
     * 從記錄中解析房間數資料
     */
    private function parseRoomsFromRecord(array $record): array
    {
        $bedrooms = (int) ($record['建物現況格局-房'] ?? $record['bedrooms'] ?? 0);
        $living_rooms = (int) ($record['建物現況格局-廳'] ?? $record['living_rooms'] ?? 0);
        $bathrooms = (int) ($record['建物現況格局-衛'] ?? $record['bathrooms'] ?? 0);

        $original = $bedrooms.'房'.$living_rooms.'廳'.$bathrooms.'衛';

        return [
            'bedrooms' => $bedrooms,
            'living_rooms' => $living_rooms,
            'bathrooms' => $bathrooms,
            'original' => $original,
        ];
    }

    /**
     * 解析房間數資料（舊方法，保留向後相容）
     */
    private function parseRooms(string $rooms): array
    {
        // 解析 "3房2廳1衛" 格式
        preg_match('/(\d+)房/', $rooms, $bedroomMatches);
        preg_match('/(\d+)廳/', $rooms, $livingRoomMatches);
        preg_match('/(\d+)衛/', $rooms, $bathroomMatches);

        return [
            'bedrooms' => (int) ($bedroomMatches[1] ?? 0),
            'living_rooms' => (int) ($livingRoomMatches[1] ?? 0),
            'bathrooms' => (int) ($bathroomMatches[1] ?? 0),
            'original' => $rooms,
        ];
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

            // 嘗試其他日期格式
            $formats = ['Y-m-d', 'Y/m/d', 'Ymd', 'Y年m月d日'];

            foreach ($formats as $format) {
                $parsed = \DateTime::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
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

        foreach ($data as $index => $record) {
            try {
                // 檢查是否已存在相同記錄
                $existing = Property::where('address', $record['address'])
                    ->where('transaction_date', $record['transaction_date'])
                    ->first();

                if ($existing) {
                    // 更新現有記錄
                    $existing->update([
                        'district' => $record['district'],
                        'road' => $record['road'],
                        'building_type' => $record['building_type'],
                        'total_price' => $record['total_price'],
                        'unit_price' => $record['unit_price'],
                        'area' => $record['area'],
                        'rooms' => $record['rooms'],
                        'floor' => $record['floor'],
                        'elevator' => $record['elevator'],
                        'purpose' => $record['purpose'],
                        'raw_data' => $record['raw_data'],
                        'updated_at' => now(),
                    ]);
                } else {
                    // 建立新記錄 - 映射到正確的資料庫欄位
                    Property::create([
                        'full_address' => $record['address'],
                        'district' => $record['district'],
                        'road' => $record['road'],
                        'building_type' => $record['building_type'],
                        'total_rent' => $record['total_price'],
                        'rent_per_month' => $record['unit_price'],
                        'total_floor_area' => $record['area'],
                        'compartment_pattern' => $record['rooms']['original'] ?? '',
                        'total_floors' => (int) $record['floor'],
                        'main_use' => $record['purpose'],
                        'rent_date' => $record['transaction_date'] ?? $this->parseDate($record['raw_data']['租賃年月日'] ?? '') ?? '2025-01-01',
                        'rental_period' => $record['raw_data']['租賃期間'] ?? '',
                        'has_management_organization' => $this->parseYesNo($record['raw_data']['有無管理組織'] ?? ''),
                        'main_building_materials' => $record['raw_data']['主要建材'] ?? '',
                        'construction_completion_year' => $this->parseConstructionYear($record['raw_data']['建築完成年月'] ?? ''),
                        'data_source' => 'government_api',
                        'is_geocoded' => false,
                        'is_processed' => true,
                    ]);
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
}
