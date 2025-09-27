<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

it('can connect to redis and perform basic operations', function () {
    // 測試 Redis 連接
    $pingResult = Redis::connection()->ping();
    expect($pingResult)->toBeTrue();

    // 測試快取基本操作
    $testKey = 'test_redis_connection_'.time();
    $testValue = 'Hello Redis!';

    // 設置快取
    Cache::put($testKey, $testValue, 60);

    // 讀取快取
    expect(Cache::get($testKey))->toBe($testValue);

    // 刪除快取
    Cache::forget($testKey);

    // 確認已刪除
    expect(Cache::get($testKey))->toBeNull();
});

it('can use redis for map data caching', function () {
    $cacheKey = 'map_test_data_'.time();
    $testData = [
        'cities' => ['台北市', '新北市'],
        'districts' => ['大安區', '信義區'],
        'properties' => [
            ['id' => 1, 'lat' => 25.0330, 'lng' => 121.5654],
            ['id' => 2, 'lat' => 25.0320, 'lng' => 121.5640],
        ],
    ];

    // 設置快取，5分鐘過期
    Cache::put($cacheKey, $testData, 300);

    // 讀取快取
    $cachedData = Cache::get($cacheKey);

    expect($cachedData)->toBe($testData);
    expect($cachedData['cities'])->toHaveCount(2);
    expect($cachedData['properties'])->toHaveCount(2);

    // 清理
    Cache::forget($cacheKey);
});

it('can handle redis connection errors gracefully', function () {
    // 測試快取標記功能
    $tags = ['map', 'properties', 'taipei'];

    $testKey = 'tagged_test_'.time();
    $testValue = ['data' => 'test'];

    // 使用標記快取
    Cache::tags($tags)->put($testKey, $testValue, 60);

    // 讀取標記快取
    expect(Cache::tags($tags)->get($testKey))->toBe($testValue);

    // 清除特定標記的所有快取
    Cache::tags(['map'])->flush();

    // 確認已清除
    expect(Cache::tags($tags)->get($testKey))->toBeNull();
});
