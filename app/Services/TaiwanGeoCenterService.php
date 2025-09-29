<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class TaiwanGeoCenterService
{
    /**
     * 台灣各縣市及鄉鎮區的地理中心點座標
     *
     * 資料來源：政府開放資料平台
     * 網址：https://data.gov.tw/dataset/7442
     * 檔案：storage/app/taiwan_geo_centers.json
     *
     * 注意：資料已遷移至 JSON 檔案以提升維護性和可讀性
     */
    private static ?array $geoCenters = null;

    /**
     * 載入地理中心點資料
     */
    private static function loadGeoCenters(): array
    {
        if (self::$geoCenters === null) {
            try {
                $jsonPath = __DIR__.'/../../database/data/taiwan_geo_centers.json';
                if (! file_exists($jsonPath)) {
                    throw new \Exception('JSON file not found at: '.$jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                if ($jsonContent === false) {
                    throw new \Exception('Failed to read JSON file');
                }

                $data = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON format: '.json_last_error_msg());
                }

                self::$geoCenters = $data['geo_centers'] ?? [];
            } catch (\Exception $e) {
                // 如果載入失敗，使用備用資料
                self::$geoCenters = self::getFallbackGeoCenters();
            }
        }

        return self::$geoCenters;
    }

    /**
     * 備用地理中心點資料（當 JSON 檔案無法載入時使用）
     */
    private static function getFallbackGeoCenters(): array
    {
        return [
            '臺北市' => [
                '中正區' => ['lat' => 25.0324, 'lng' => 121.5194],
                '大同區' => ['lat' => 25.0631, 'lng' => 121.5125],
                '中山區' => ['lat' => 25.0640, 'lng' => 121.5266],
                '松山區' => ['lat' => 25.0500, 'lng' => 121.5775],
                '大安區' => ['lat' => 25.0264, 'lng' => 121.5436],
                '萬華區' => ['lat' => 25.0359, 'lng' => 121.4991],
                '信義區' => ['lat' => 25.0320, 'lng' => 121.5654],
                '士林區' => ['lat' => 25.0884, 'lng' => 121.5256],
                '北投區' => ['lat' => 25.1324, 'lng' => 121.4985],
                '內湖區' => ['lat' => 25.0697, 'lng' => 121.5948],
                '南港區' => ['lat' => 25.0547, 'lng' => 121.6071],
                '文山區' => ['lat' => 24.9981, 'lng' => 121.5701],
            ],
            '花蓮縣' => [
                '花蓮市' => ['lat' => 23.9731, 'lng' => 121.6014],
                '鳳林鎮' => ['lat' => 23.7333, 'lng' => 121.4500],
                '玉里鎮' => ['lat' => 23.3333, 'lng' => 121.3167],
            ],
        ];
    }

    /**
     * 取得指定縣市和行政區的地理中心點
     */
    public static function getGeoCenter(string $city, string $district): ?array
    {
        $geoCenters = self::loadGeoCenters();

        return $geoCenters[$city][$district] ?? null;
    }

    /**
     * 取得指定縣市的所有行政區列表
     */
    public static function getDistrictsByCity(string $city): array
    {
        $geoCenters = self::loadGeoCenters();

        return array_keys($geoCenters[$city] ?? []);
    }

    /**
     * 取得所有縣市列表
     */
    public static function getAllCities(): array
    {
        $geoCenters = self::loadGeoCenters();

        return array_keys($geoCenters);
    }

    /**
     * 取得指定縣市的中心點（使用第一個行政區的中心點）
     */
    public static function getCityCenter(string $city): ?array
    {
        $geoCenters = self::loadGeoCenters();
        $districts = $geoCenters[$city] ?? [];

        if (empty($districts)) {
            return null;
        }

        // 返回第一個行政區的中心點作為縣市中心點
        return array_values($districts)[0];
    }

    /**
     * 取得指定行政區的邊界範圍（基於中心點計算的近似邊界）
     */
    public static function getDistrictBounds(string $district): ?array
    {
        $geoCenters = self::loadGeoCenters();

        // 遍歷所有縣市尋找該行政區
        foreach ($geoCenters as $city => $districts) {
            if (isset($districts[$district])) {
                $center = $districts[$district];
                $lat = $center['lat'];
                $lng = $center['lng'];

                // 計算約 5km 的半徑邊界
                $latOffset = 0.045; // 約 5km
                $lngOffset = 0.045; // 約 5km

                return [
                    'north' => $lat + $latOffset,
                    'south' => $lat - $latOffset,
                    'east' => $lng + $lngOffset,
                    'west' => $lng - $lngOffset,
                ];
            }
        }

        return null;
    }

    /**
     * 檢查縣市是否存在
     */
    public static function cityExists(string $city): bool
    {
        $geoCenters = self::loadGeoCenters();

        return isset($geoCenters[$city]);
    }

    /**
     * 檢查行政區是否存在於指定縣市
     */
    public static function districtExists(string $city, string $district): bool
    {
        $geoCenters = self::loadGeoCenters();

        return isset($geoCenters[$city][$district]);
    }

    /**
     * 取得資料來源資訊
     */
    public static function getDataSourceInfo(): array
    {
        try {
            $jsonContent = Storage::get('taiwan_geo_centers.json');
            $data = json_decode($jsonContent, true);

            return [
                'data_source' => $data['data_source'] ?? '政府開放資料平台',
                'source_url' => $data['source_url'] ?? 'https://data.gov.tw/dataset/7442',
                'last_updated' => $data['last_updated'] ?? '2024-01-01',
                'description' => $data['description'] ?? '台灣各縣市及鄉鎮區的地理中心點座標資料',
            ];
        } catch (\Exception $e) {
            return [
                'data_source' => '政府開放資料平台',
                'source_url' => 'https://data.gov.tw/dataset/7442',
                'last_updated' => '2024-01-01',
                'description' => '台灣各縣市及鄉鎮區的地理中心點座標資料',
            ];
        }
    }
}
