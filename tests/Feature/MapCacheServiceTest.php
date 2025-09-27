<?php

use App\Services\MapCacheService;

it('can cache and retrieve map rentals data', function () {
    $cacheService = new MapCacheService;

    $filters = ['city' => '台北市', 'district' => '大安區'];
    $testData = [
        'rentals' => [
            ['id' => 'taipei_daan_1', 'lat' => 25.0330, 'lng' => 121.5654],
            ['id' => 'taipei_daan_2', 'lat' => 25.0320, 'lng' => 121.5640],
        ],
        'statistics' => ['count' => 2],
    ];

    // 測試快取
    $cacheService->cacheMapRentals($filters, $testData);

    // 測試讀取
    $cachedData = $cacheService->getCachedMapRentals($filters);

    expect($cachedData)->toBe($testData);
    expect($cachedData['rentals'])->toHaveCount(2);
});

it('can cache and retrieve cities and districts', function () {
    $cacheService = new MapCacheService;

    // 測試城市快取
    $cities = ['台北市', '新北市', '桃園市'];
    $cacheService->cacheCities($cities);

    $cachedCities = $cacheService->getCachedCities();
    expect($cachedCities)->toBe($cities);

    // 測試行政區快取
    $districts = ['大安區', '信義區', '松山區'];
    $cacheService->cacheDistricts('台北市', $districts);

    $cachedDistricts = $cacheService->getCachedDistricts('台北市');
    expect($cachedDistricts)->toBe($districts);
});

it('can cache and retrieve AI predictions', function () {
    $cacheService = new MapCacheService;

    $input = [
        'properties' => [
            ['lat' => 25.0330, 'lng' => 121.5654, 'area' => 25.5],
            ['lat' => 25.0320, 'lng' => 121.5640, 'area' => 30.0],
        ],
    ];

    $prediction = [
        'predictions' => [
            'items' => [
                ['price' => 2500, 'confidence' => 0.85],
                ['price' => 2800, 'confidence' => 0.90],
            ],
            'summary' => ['average_confidence' => 0.875],
        ],
        'model_info' => ['version' => '1.0'],
    ];

    // 測試快取
    $cacheService->cacheAIPrediction($input, $prediction);

    // 測試讀取
    $cachedPrediction = $cacheService->getCachedAIPrediction($input);

    expect($cachedPrediction)->toBe($prediction);
    expect($cachedPrediction['predictions']['items'])->toHaveCount(2);
});

it('can clear all map cache', function () {
    $cacheService = new MapCacheService;

    // 設置測試快取
    $testData = ['rentals' => [['id' => 'test_1']]];
    $cacheService->cacheMapRentals(['city' => '台北市'], $testData);
    $cacheService->cacheCities(['台北市', '新北市']);

    // 確認快取存在
    expect($cacheService->getCachedMapRentals(['city' => '台北市']))->toBe($testData);
    expect($cacheService->getCachedCities())->toBe(['台北市', '新北市']);

    // 清除所有快取
    $cacheService->clearAllMapCache();

    // 確認快取被清除
    expect($cacheService->getCachedMapRentals(['city' => '台北市']))->toBeNull();
    expect($cacheService->getCachedCities())->toBeNull();
});

it('can provide cache statistics', function () {
    $cacheService = new MapCacheService;

    // 清除所有快取以確保乾淨的測試環境
    $cacheService->clearAllMapCache();

    // 設置一些測試快取
    $cacheService->cacheCities(['台北市', '新北市']);
    $cacheService->cacheDistricts('台北市', ['大安區', '信義區']);
    $cacheService->cacheMapRentals(['city' => '台北市'], ['rentals' => []]);

    // 取得統計資訊
    $stats = $cacheService->getCacheStats();

    expect($stats)->toHaveKey('total_keys');
    expect($stats)->toHaveKey('memory_usage');
    expect($stats)->toHaveKey('key_types');
    expect($stats['total_keys'])->toBeGreaterThanOrEqual(0);
});

it('can check redis connection status', function () {
    $cacheService = new MapCacheService;

    expect($cacheService->isConnected())->toBeTrue();
});
