<?php

namespace Tests\Feature;

use App\Support\PerformanceMonitor;
use Tests\TestCase;

class PerformanceMonitoringTest extends TestCase
{
    public function test_track_model_records_metrics_and_warnings(): void
    {
        $monitor = PerformanceMonitor::start('consistency-test');

        $result = $monitor->trackModel('price_prediction', function () {
            usleep(200_000);
            return 'ok';
        }, ['threshold_ms' => 50]);

        $this->assertSame('ok', $result);

        $summary = $monitor->summary();
        $this->assertArrayHasKey('models', $summary);
        $this->assertArrayHasKey('price_prediction', $summary['models']);
        $this->assertNotEmpty($summary['warnings']);

        $entry = $summary['models']['price_prediction'][0];
        $this->assertArrayHasKey('duration_ms', $entry);
        $this->assertArrayHasKey('memory_mb', $entry);
    }

    public function test_custom_warnings_can_be_registered(): void
    {
        $monitor = PerformanceMonitor::start('warning-test');
        $monitor->addWarning('memory threshold exceeded', ['limit_mb' => 128]);

        $summary = $monitor->summary();
        $this->assertNotEmpty($summary['warnings']);
        $this->assertEquals('memory threshold exceeded', $summary['warnings'][0]['message']);
        $this->assertEquals('custom', $summary['warnings'][0]['type']);
    }
}
