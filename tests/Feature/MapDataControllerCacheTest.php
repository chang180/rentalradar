<?php

use App\Models\Property;
use Illuminate\Support\Facades\Cache;

it('can cache map rentals data and return cached results', function () {
    // 清除快取以確保乾淨的測試環境
    Cache::flush();

    // 建立測試資料
    Property::factory()->count(5)->create([
        'city' => '台北市',
        'district' => '大安區',
        'latitude' => 25.0330,
        'longitude' => 121.5654,
    ]);

    // 第一次請求 - 應該從資料庫查詢
    $response1 = $this->getJson('/api/map/rentals?city=台北市&district=大安區');

    $response1->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'data' => [
                'rentals',
                'statistics',
            ],
            'meta' => [
                'performance',
                'aggregation_type',
            ],
        ]);

    // 第二次請求 - 應該從快取返回
    $response2 = $this->getJson('/api/map/rentals?city=台北市&district=大安區');

    $response2->assertSuccessful();

    // 確認回應內容相同
    expect($response2->json('data'))->toBe($response1->json('data'));
});

it('can cache cities data', function () {
    Cache::flush();

    // 第一次請求
    $response1 = $this->getJson('/api/map/cities');
    $response1->assertSuccessful();

    // 第二次請求應該從快取返回
    $response2 = $this->getJson('/api/map/cities');
    $response2->assertSuccessful();

    expect($response2->json('data'))->toBe($response1->json('data'));
});

it('can cache districts data', function () {
    Cache::flush();

    // 第一次請求
    $response1 = $this->getJson('/api/map/districts?city=台北市');
    $response1->assertSuccessful();

    // 第二次請求應該從快取返回
    $response2 = $this->getJson('/api/map/districts?city=台北市');
    $response2->assertSuccessful();

    expect($response2->json('data'))->toBe($response1->json('data'));
});

it('can cache statistics data', function () {
    Cache::flush();

    // 建立測試資料
    Property::factory()->count(3)->create([
        'city' => '台北市',
        'district' => '信義區',
        'latitude' => 25.0320,
        'longitude' => 121.5640,
    ]);

    // 第一次請求
    $response1 = $this->getJson('/api/map/statistics?city=台北市');
    $response1->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'data' => [
                'total_properties',
                'total_districts',
                'total_cities',
                'avg_rent_per_ping',
                'min_rent_per_ping',
                'max_rent_per_ping',
                'cities',
            ],
            'meta' => ['performance'],
        ]);

    // 第二次請求應該從快取返回
    $response2 = $this->getJson('/api/map/statistics?city=台北市');
    $response2->assertSuccessful();

    expect($response2->json('data'))->toBe($response1->json('data'));
});

it('returns different cached data for different filter parameters', function () {
    Cache::flush();

    // 建立不同城市的測試資料
    Property::factory()->count(2)->create([
        'city' => '台北市',
        'district' => '大安區',
        'latitude' => 25.0330,
        'longitude' => 121.5654,
    ]);

    Property::factory()->count(3)->create([
        'city' => '新北市',
        'district' => '板橋區',
        'latitude' => 25.0139,
        'longitude' => 121.4633,
    ]);

    // 請求台北市資料
    $taipeiResponse = $this->getJson('/api/map/rentals?city=台北市');
    $taipeiResponse->assertSuccessful();

    // 請求新北市資料
    $newTaipeiResponse = $this->getJson('/api/map/rentals?city=新北市');
    $newTaipeiResponse->assertSuccessful();

    // 確認兩個回應的資料不同
    expect($taipeiResponse->json('data'))->not->toBe($newTaipeiResponse->json('data'));

    // 再次請求台北市資料，應該從快取返回
    $taipeiResponse2 = $this->getJson('/api/map/rentals?city=台北市');
    expect($taipeiResponse2->json('data'))->toBe($taipeiResponse->json('data'));
});
