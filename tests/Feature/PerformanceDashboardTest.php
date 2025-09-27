<?php

namespace Tests\Feature;

use App\Services\ErrorTrackingService;
use App\Services\UserBehaviorTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_dashboard_overview(): void
    {
        $response = $this->getJson('/api/dashboard/overview');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'time_range',
                    'performance',
                    'errors',
                    'user_behavior',
                    'timestamp',
                ],
            ]);
    }

    public function test_can_log_error(): void
    {
        $errorData = [
            'level' => 'error',
            'message' => 'Test error message',
            'url' => '/test-page',
            'user_id' => 'test-user-1',
        ];

        $response = $this->postJson('/api/dashboard/log-error', $errorData);

        $response->assertSuccessful()
            ->assertJson([
                'success' => true,
                'message' => 'Error logged successfully',
            ]);
    }

    public function test_can_track_user_behavior(): void
    {
        $behaviorData = [
            'action' => 'page_view',
            'page' => '/dashboard',
            'duration' => 30000,
            'user_id' => 'test-user-1',
            'session_id' => 'test-session-1',
        ];

        $response = $this->postJson('/api/dashboard/track-behavior', $behaviorData);

        $response->assertSuccessful()
            ->assertJson([
                'success' => true,
                'message' => 'Behavior tracked successfully',
            ]);
    }

    public function test_can_get_error_logs(): void
    {
        // 先記錄一些錯誤
        $this->postJson('/api/dashboard/log-error', [
            'level' => 'error',
            'message' => 'Test error 1',
        ]);

        $this->postJson('/api/dashboard/log-error', [
            'level' => 'warning',
            'message' => 'Test warning 1',
        ]);

        $response = $this->getJson('/api/dashboard/errors');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'errors',
                    'total',
                    'filters',
                ],
            ]);
    }

    public function test_can_get_user_behaviors(): void
    {
        // 先記錄一些行為
        $this->postJson('/api/dashboard/track-behavior', [
            'action' => 'page_view',
            'page' => '/dashboard',
            'user_id' => 'test-user-1',
        ]);

        $response = $this->getJson('/api/dashboard/behaviors');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'behaviors',
                    'total',
                    'filters',
                ],
            ]);
    }

    public function test_can_get_system_health(): void
    {
        $response = $this->getJson('/api/dashboard/system-health');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'health_score',
                    'status',
                    'error_rate',
                    'response_time',
                    'memory_usage',
                    'timestamp',
                ],
            ]);
    }

    public function test_can_get_realtime_metrics(): void
    {
        $response = $this->getJson('/api/dashboard/realtime-metrics');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data',
                'timestamp',
            ]);
    }

    public function test_can_cleanup_old_data(): void
    {
        $response = $this->postJson('/api/dashboard/cleanup', [
            'days_to_keep' => 7,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'errors_cleaned',
                    'behaviors_cleaned',
                    'days_kept',
                ],
            ]);
    }

    public function test_error_tracking_service_works(): void
    {
        $errorTracking = app(ErrorTrackingService::class);

        $errorTracking->logError([
            'level' => 'error',
            'message' => 'Test error',
            'url' => '/test',
        ]);

        $stats = $errorTracking->getErrorStats('1h');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_errors', $stats);
    }

    public function test_user_behavior_tracking_service_works(): void
    {
        $behaviorTracking = app(UserBehaviorTrackingService::class);

        $behaviorTracking->trackBehavior([
            'action' => 'page_view',
            'page' => '/test',
            'user_id' => 'test-user',
        ]);

        $stats = $behaviorTracking->getUserBehaviorStats('1h');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_actions', $stats);
    }
}
