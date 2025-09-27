<?php

namespace App\Console\Commands;

use App\Services\GeoAggregationService;
use App\Services\TaiwanGeoCenterService;
use Illuminate\Console\Command;

class TestGeoAggregation extends Command
{
    protected $signature = 'test:geo-aggregation';

    protected $description = '測試地理聚合服務';

    public function handle(): int
    {
        $this->info('測試地理聚合服務...');

        // 測試台灣地理中心點服務
        $this->info('1. 測試台灣地理中心點服務');
        $cities = TaiwanGeoCenterService::getCities();
        $this->info('找到 '.count($cities).' 個縣市');

        $taipeiDistricts = TaiwanGeoCenterService::getDistricts('臺北市');
        $this->info('台北市有 '.count($taipeiDistricts).' 個行政區');

        $center = TaiwanGeoCenterService::getCenter('臺北市', '中正區');
        if ($center) {
            $this->info('台北市中正區中心點: '.$center['lat'].', '.$center['lng']);
        }

        // 測試地理聚合服務
        $this->info('2. 測試地理聚合服務');
        $geoService = app(GeoAggregationService::class);
        $aggregatedData = $geoService->getAggregatedProperties();

        $this->info('找到 '.$aggregatedData->count().' 個聚合區域');

        if ($aggregatedData->count() > 0) {
            $first = $aggregatedData->first();
            $this->info('第一個聚合區域: '.$first['city'].$first['district']);
            $this->info('物件數量: '.$first['property_count']);
            $this->info('平均租金: '.$first['avg_rent']);
            $this->info('有座標: '.($first['has_coordinates'] ? '是' : '否'));
        }

        // 測試熱門區域
        $this->info('3. 測試熱門區域');
        $popularDistricts = $geoService->getPopularDistricts(5);
        $this->info('前5個熱門區域:');
        foreach ($popularDistricts as $district) {
            $this->info('- '.$district['city'].$district['district'].': '.$district['property_count'].' 筆');
        }

        $this->info('測試完成！');

        return 0;
    }
}
