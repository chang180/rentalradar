<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * 租屋資料處理服務
 *
 * 提供租屋市場資料的清理、驗證和格式化功能
 */
class RentalDataProcessingService
{
    /**
     * 支援的資料格式
     */
    private const SUPPORTED_FORMATS = ['csv', 'xml', 'json'];

    /**
     * 資料驗證規則
     */
    private const VALIDATION_RULES = [
        'price' => ['required', 'numeric', 'min:1000'],
        'address' => ['required', 'string', 'max:255'],
        'area' => ['required', 'numeric', 'min:1'],
        'room_count' => ['required', 'integer', 'min:1'],
    ];

    public function __construct()
    {
        //
    }

    /**
     * 處理租屋資料
     *
     * @param  array  $data  原始資料
     * @param  string  $format  資料格式
     * @return array 處理後的資料
     *
     * @throws InvalidArgumentException
     */
    public function processRentalData(array $data, string $format = 'json'): array
    {
        $this->validateFormat($format);

        $cleanedData = $this->cleanData($data);
        $validatedData = $this->validateData($cleanedData);
        $normalizedData = $this->normalizeData($validatedData);

        return $normalizedData;
    }

    /**
     * 清理資料
     */
    private function cleanData(array $data): array
    {
        return array_map(function ($item) {
            // 移除空白字符
            if (isset($item['address'])) {
                $item['address'] = trim($item['address']);
            }

            // 處理價格格式
            if (isset($item['price'])) {
                $item['price'] = (float) preg_replace('/[^\d.]/', '', $item['price']);
            }

            // 處理坪數
            if (isset($item['area'])) {
                $item['area'] = (float) preg_replace('/[^\d.]/', '', $item['area']);
            }

            // 處理房間數
            if (isset($item['room_count'])) {
                $item['room_count'] = (int) preg_replace('/[^\d]/', '', $item['room_count']);
            }

            return $item;
        }, $data);
    }

    /**
     * 驗證資料
     *
     * @throws InvalidArgumentException
     */
    private function validateData(array $data): array
    {
        $validData = [];

        foreach ($data as $index => $item) {
            $isValid = true;

            // 檢查必要欄位
            foreach (self::VALIDATION_RULES as $field => $rules) {
                if (! isset($item[$field])) {
                    $isValid = false;
                    break;
                }

                // 驗證價格範圍
                if ($field === 'price' && $item[$field] < 1000) {
                    $isValid = false;
                    break;
                }

                // 驗證坪數
                if ($field === 'area' && $item[$field] < 1) {
                    $isValid = false;
                    break;
                }

                // 驗證房間數
                if ($field === 'room_count' && $item[$field] < 1) {
                    $isValid = false;
                    break;
                }
            }

            if ($isValid) {
                $validData[] = $item;
            }
        }

        return $validData;
    }

    /**
     * 正規化資料
     */
    private function normalizeData(array $data): array
    {
        return array_map(function ($item) {
            // 計算每坪價格
            if (isset($item['price']) && isset($item['area']) && $item['area'] > 0) {
                $item['price_per_ping'] = round($item['price'] / $item['area'], 2);
            }

            // 標準化地址格式
            if (isset($item['address'])) {
                $item['normalized_address'] = $this->normalizeAddress($item['address']);
            }

            // 房型分類
            if (isset($item['room_count'])) {
                $item['room_type'] = $this->categorizeRoomType($item['room_count']);
            }

            // 添加處理時間戳
            $item['processed_at'] = now()->toISOString();

            return $item;
        }, $data);
    }

    /**
     * 驗證資料格式
     *
     * @throws InvalidArgumentException
     */
    private function validateFormat(string $format): void
    {
        if (! in_array(strtolower($format), self::SUPPORTED_FORMATS)) {
            throw new InvalidArgumentException(
                sprintf('不支援的格式: %s。支援的格式: %s', $format, implode(', ', self::SUPPORTED_FORMATS))
            );
        }
    }

    /**
     * 正規化地址
     */
    private function normalizeAddress(string $address): string
    {
        // 移除多餘空白
        $address = preg_replace('/\s+/', ' ', trim($address));

        // 標準化台灣地址格式
        $address = str_replace(['台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市'],
            ['台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市'], $address);

        return $address;
    }

    /**
     * 房型分類
     */
    private function categorizeRoomType(int $roomCount): string
    {
        return match ($roomCount) {
            1 => '套房',
            2 => '2房',
            3 => '3房',
            4 => '4房',
            default => $roomCount >= 5 ? '5房以上' : '其他',
        };
    }

    /**
     * 取得處理統計
     */
    public function getProcessingStats(array $originalData, array $processedData): array
    {
        $originalCount = count($originalData);
        $processedCount = count($processedData);

        return [
            'original_count' => $originalCount,
            'processed_count' => $processedCount,
            'success_rate' => $originalCount > 0 ? round(($processedCount / $originalCount) * 100, 2) : 0,
            'filtered_count' => $originalCount - $processedCount,
        ];
    }
}
