<?php

namespace App\Jobs;

use App\Services\StatisticsUpdateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateDistrictStatisticsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $city,
        public string $district
    ) {}

    public function handle(StatisticsUpdateService $service): void
    {
        try {
            $service->updateDistrictStatistics($this->city, $this->district);

            Log::info('District statistics updated successfully', [
                'city' => $this->city,
                'district' => $this->district,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update district statistics', [
                'city' => $this->city,
                'district' => $this->district,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
