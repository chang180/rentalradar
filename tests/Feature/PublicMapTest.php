<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_public_map_without_authentication(): void
    {
        $response = $this->get('/public-map');

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('map')
            ->where('is_public', true)
            ->has('remaining_seconds')
            ->has('ip_usage_data')
        );
    }

    public function test_public_map_tracks_session_correctly(): void
    {
        // 清除快取
        Cache::flush();

        // 第一次訪問
        $response1 = $this->get('/public-map');
        $response1->assertSuccessful();

        // 檢查會話已建立
        $sessionKey = 'public_map_session_127.0.0.1';
        $this->assertTrue(Cache::has($sessionKey));

        // 等待一秒後再次訪問（模擬頁面刷新）
        sleep(1);
        $response2 = $this->get('/public-map');
        $response2->assertSuccessful();

        // 檢查會話持續時間增加了
        $session = Cache::get($sessionKey);
        $this->assertNotNull($session['last_activity']);
    }

    public function test_public_map_respects_daily_limit(): void
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

        $response = $this->get('/public-map');

        $response->assertRedirect('/');
        $response->assertSessionHas('error', '您今日的免費試用時間已用完（30分鐘），請註冊帳號以獲得完整功能。');
    }

    public function test_public_map_session_end_api_works(): void
    {
        // 清除快取
        Cache::flush();

        // 建立一個會話
        $sessionKey = 'public_map_session_127.0.0.1';
        $cacheKey = 'public_map_usage_127.0.0.1';

        Cache::put($sessionKey, [
            'start_time' => now()->subMinutes(5),
            'last_activity' => now()->subMinutes(1),
        ], now()->addHours(2));

        // 呼叫會話結束 API
        $response = $this->postJson('/api/public-map/session-end');

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);

        // 檢查會話已被清理
        $this->assertFalse(Cache::has($sessionKey));

        // 檢查使用時間已被記錄
        $usageData = Cache::get($cacheKey);
        $this->assertNotNull($usageData);
        $this->assertGreaterThan(0, $usageData['used_seconds']);
    }

    public function test_public_map_calculates_remaining_time_correctly(): void
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

        $response = $this->get('/public-map');

        $response->assertSuccessful();

        // 檢查剩餘時間約為 20 分鐘（1200 秒）
        $response->assertInertia(fn ($page) => $page
            ->where('remaining_seconds', 1200)
        );
    }

    public function test_public_map_handles_session_expiry(): void
    {
        // 清除快取
        Cache::flush();

        // 建立一個過期的會話（超過 30 分鐘無活動），但總使用時間不超過限制
        $sessionKey = 'public_map_session_127.0.0.1';
        $cacheKey = 'public_map_usage_127.0.0.1';

        // 先設置較少的使用時間，這樣即使會話過期也不會超過 30 分鐘限制
        Cache::put($cacheKey, [
            'date' => now()->format('Y-m-d'),
            'used_seconds' => 300, // 5 分鐘
            'sessions' => [],
        ], now()->endOfDay());

        Cache::put($sessionKey, [
            'start_time' => now()->subMinutes(20), // 20 分鐘前開始，但 35 分鐘前無活動
            'last_activity' => now()->subMinutes(35), // 35 分鐘前最後活動
        ], now()->addHours(2));

        $response = $this->get('/public-map');

        $response->assertSuccessful();

        // 檢查新會話已建立
        $newSession = Cache::get($sessionKey);
        $this->assertNotNull($newSession);
        $this->assertNotNull($newSession['start_time']);

        // 檢查新會話的開始時間是最近的（不是 25 分鐘前）
        $sessionStartTime = $newSession['start_time'];
        $timeDiff = now()->diffInMinutes($sessionStartTime);
        $this->assertLessThan(5, $timeDiff, '新會話應該是在最近 5 分鐘內開始的');
    }

    public function test_public_map_daily_reset_works(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬昨天的使用記錄
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [
            'date' => now()->subDay()->format('Y-m-d'), // 昨天的日期
            'used_seconds' => 1800, // 昨天已用 30 分鐘
            'sessions' => [],
        ], now()->endOfDay());

        $response = $this->get('/public-map');

        $response->assertSuccessful();

        // 檢查使用時間已重置
        $response->assertInertia(fn ($page) => $page
            ->where('remaining_seconds', 1800) // 應該重新開始 30 分鐘
        );
    }

    public function test_public_map_handles_incomplete_cache_data(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬不完整的快取資料（缺少 'date' 鍵）
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [
            'used_seconds' => 600, // 只有這個鍵，缺少 'date' 和 'sessions'
        ], now()->endOfDay());

        $response = $this->get('/public-map');

        $response->assertSuccessful();

        // 檢查系統能正常處理不完整的快取資料
        $response->assertInertia(fn ($page) => $page
            ->component('map')
            ->where('is_public', true)
            ->has('remaining_seconds')
            ->has('ip_usage_data')
        );
    }

    public function test_public_map_handles_empty_cache(): void
    {
        // 清除快取
        Cache::flush();

        // 模擬完全空的快取
        $cacheKey = 'public_map_usage_127.0.0.1';
        Cache::put($cacheKey, [], now()->endOfDay());

        $response = $this->get('/public-map');

        $response->assertSuccessful();

        // 檢查系統能正常處理空快取
        $response->assertInertia(fn ($page) => $page
            ->component('map')
            ->where('is_public', true)
            ->where('remaining_seconds', 1800) // 應該是完整的 30 分鐘
        );
    }
}
