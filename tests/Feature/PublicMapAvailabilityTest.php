<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicMapAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_check_availability_when_not_used(): void
    {
        // 清除快取
        Cache::flush();

        $response = $this->getJson('/api/public-map/availability');

        $response->assertSuccessful();
        $response->assertJson([
            'is_available' => true,
            'remaining_seconds' => 1800,
            'used_seconds' => 0,
            'daily_limit_seconds' => 1800,
        ]);
    }

    public function test_can_check_availability_when_partially_used(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬已使用 10 分鐘的情況
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [
            'date' => now()->format('Y-m-d'),
            'used_seconds' => 600, // 10 分鐘
            'sessions' => [],
        ], now()->endOfDay());

        $response = $this->getJson('/api/public-map/availability');

        $response->assertSuccessful();
        $response->assertJson([
            'is_available' => true,
            'remaining_seconds' => 1200, // 20 分鐘
            'used_seconds' => 600,
            'daily_limit_seconds' => 1800,
        ]);
    }

    public function test_can_check_availability_when_daily_limit_reached(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬已使用 30 分鐘的情況
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [
            'date' => now()->format('Y-m-d'),
            'used_seconds' => 1800, // 30 分鐘
            'sessions' => [],
        ], now()->endOfDay());

        $response = $this->getJson('/api/public-map/availability');

        $response->assertSuccessful();
        $response->assertJson([
            'is_available' => false,
            'remaining_seconds' => 0,
            'used_seconds' => 1800,
            'daily_limit_seconds' => 1800,
        ]);
    }

    public function test_resets_usage_on_new_day(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬昨天的使用記錄
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [
            'date' => now()->subDay()->format('Y-m-d'),
            'used_seconds' => 1800, // 昨天已用完
            'sessions' => [],
        ], now()->endOfDay());

        $response = $this->getJson('/api/public-map/availability');

        $response->assertSuccessful();
        $response->assertJson([
            'is_available' => true,
            'remaining_seconds' => 1800, // 新的一天，重置
            'used_seconds' => 0,
            'daily_limit_seconds' => 1800,
        ]);
    }
}
