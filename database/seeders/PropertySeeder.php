<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertySeeder extends Seeder
{
    /**
     * 建立測試用的租賃物件資料
     */
    public function run(): void
    {
        // 清除現有資料
        Property::truncate();

        // 台北市各行政區的測試資料
        $districts = [
            '中正區', '大同區', '中山區', '松山區', '大安區',
            '萬華區', '信義區', '士林區', '北投區', '內湖區',
            '南港區', '文山區'
        ];

        $buildingTypes = [
            '住宅大樓', '公寓', '透天厝', '華廈', '套房'
        ];

        $mainUses = [
            '住家用', '商業用', '辦公', '工業用', '其他'
        ];

        $mainMaterials = [
            '鋼筋混凝土造', '鋼骨造', '磚造', '木造', '其他'
        ];

        $compartmentPatterns = [
            '1房1廳1衛', '2房1廳1衛', '2房2廳1衛', '3房2廳2衛', '4房2廳2衛'
        ];

        $properties = [];

        foreach ($districts as $district) {
            // 每個行政區建立 20-50 個物件
            $count = rand(20, 50);
            
            for ($i = 0; $i < $count; $i++) {
                $properties[] = [
                    'district' => $district,
                    'village' => '測試里',
                    'road' => $this->generateAddress($district),
                    'land_section' => '測試段',
                    'land_subsection' => '測試小段',
                    'land_number' => rand(1, 999),
                    'building_type' => $buildingTypes[array_rand($buildingTypes)],
                    'total_floor_area' => rand(15, 100),
                    'main_use' => $mainUses[array_rand($mainUses)],
                    'main_building_materials' => $mainMaterials[array_rand($mainMaterials)],
                    'construction_completion_year' => rand(1990, 2024),
                    'total_floors' => rand(1, 20),
                    'compartment_pattern' => $compartmentPatterns[array_rand($compartmentPatterns)],
                    'has_management_organization' => rand(0, 1),
                    'rent_per_month' => $this->generateRentPrice($district),
                    'total_rent' => $this->generateRentPrice($district),
                    'rent_date' => $this->generateRentDate(),
                    'rental_period' => rand(1, 12) . '個月',
                    'latitude' => $this->generateLatitude($district),
                    'longitude' => $this->generateLongitude($district),
                    'full_address' => $this->generateFullAddress($district),
                    'is_geocoded' => true,
                    'data_source' => 'test',
                    'is_processed' => true,
                    'processing_notes' => json_encode(['source' => 'test_seeder']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // 批量插入
        DB::table('properties')->insert($properties);
    }

    /**
     * 生成地址
     */
    private function generateAddress(string $district): string
    {
        $roads = [
            '中正區' => ['重慶南路', '博愛路', '衡陽路', '武昌街'],
            '大同區' => ['民生西路', '迪化街', '延平北路', '重慶北路'],
            '中山區' => ['南京東路', '民生東路', '松江路', '建國北路'],
            '松山區' => ['八德路', '民生東路', '南京東路', '敦化北路'],
            '大安區' => ['信義路', '仁愛路', '敦化南路', '復興南路'],
            '萬華區' => ['西門町', '艋舺大道', '環河南路', '和平西路'],
            '信義區' => ['信義路', '松仁路', '松高路', '松智路'],
            '士林區' => ['士林夜市', '中正路', '文林路', '承德路'],
            '北投區' => ['石牌路', '明德路', '中央北路', '大業路'],
            '內湖區' => ['內湖路', '成功路', '民權東路', '瑞光路'],
            '南港區' => ['南港路', '忠孝東路', '研究院路', '重陽路'],
            '文山區' => ['木柵路', '景美街', '興隆路', '辛亥路'],
        ];

        $districtRoads = $roads[$district] ?? ['主要道路'];
        $road = $districtRoads[array_rand($districtRoads)];
        $number = rand(1, 999);

        return "{$road}{$number}號";
    }

    /**
     * 生成租金價格
     */
    private function generateRentPrice(string $district): int
    {
        // 根據行政區設定不同的價格範圍
        $priceRanges = [
            '大安區' => [25000, 80000],
            '信義區' => [30000, 100000],
            '松山區' => [20000, 70000],
            '中山區' => [18000, 65000],
            '中正區' => [15000, 60000],
            '大同區' => [12000, 45000],
            '萬華區' => [10000, 40000],
            '士林區' => [15000, 55000],
            '北投區' => [12000, 45000],
            '內湖區' => [15000, 50000],
            '南港區' => [13000, 48000],
            '文山區' => [10000, 40000],
        ];

        $range = $priceRanges[$district] ?? [10000, 50000];
        return rand($range[0], $range[1]);
    }

    /**
     * 生成租賃日期
     */
    private function generateRentDate(): string
    {
        $startDate = now()->subMonths(6);
        $endDate = now();
        $randomTimestamp = rand($startDate->timestamp, $endDate->timestamp);
        
        return date('Y-m-d', $randomTimestamp);
    }

    /**
     * 生成緯度
     */
    private function generateLatitude(string $district): float
    {
        $latRanges = [
            '中正區' => [25.03, 25.05],
            '大同區' => [25.06, 25.08],
            '中山區' => [25.05, 25.07],
            '松山區' => [25.04, 25.06],
            '大安區' => [25.02, 25.04],
            '萬華區' => [25.03, 25.05],
            '信義區' => [25.01, 25.03],
            '士林區' => [25.08, 25.10],
            '北投區' => [25.12, 25.14],
            '內湖區' => [25.06, 25.08],
            '南港區' => [25.04, 25.06],
            '文山區' => [24.98, 25.00],
        ];

        $range = $latRanges[$district] ?? [25.0, 25.1];
        return $range[0] + (rand(0, 1000) / 1000) * ($range[1] - $range[0]);
    }

    /**
     * 生成經度
     */
    private function generateLongitude(string $district): float
    {
        $lngRanges = [
            '中正區' => [121.50, 121.52],
            '大同區' => [121.51, 121.53],
            '中山區' => [121.52, 121.54],
            '松山區' => [121.53, 121.55],
            '大安區' => [121.54, 121.56],
            '萬華區' => [121.50, 121.52],
            '信義區' => [121.55, 121.57],
            '士林區' => [121.52, 121.54],
            '北投區' => [121.50, 121.52],
            '內湖區' => [121.56, 121.58],
            '南港區' => [121.58, 121.60],
            '文山區' => [121.54, 121.56],
        ];

        $range = $lngRanges[$district] ?? [121.5, 121.6];
        return $range[0] + (rand(0, 1000) / 1000) * ($range[1] - $range[0]);
    }

    /**
     * 生成完整地址
     */
    private function generateFullAddress(string $district): string
    {
        $road = $this->generateAddress($district);
        return "台北市{$district}{$road}";
    }
}
