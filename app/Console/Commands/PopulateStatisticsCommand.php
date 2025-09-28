<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\StatisticsUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateStatisticsCommand extends Command
{
    protected $signature = 'statistics:populate
                            {--chunk=1000 : Number of records to process at once}
                            {--city= : Specific city to process}
                            {--district= : Specific district to process}';

    protected $description = 'Populate district and city statistics from existing property data';

    public function handle(StatisticsUpdateService $statisticsService): void
    {
        $this->info('Starting statistics population...');

        $chunkSize = (int) $this->option('chunk');
        $city = $this->option('city');
        $district = $this->option('district');

        if ($city && $district) {
            $this->info("Processing specific district: {$city} - {$district}");
            $statisticsService->updateDistrictStatistics($city, $district);
            $this->info('District statistics updated successfully!');

            return;
        }

        $query = Property::query()
            ->select('city', 'district')
            ->distinct();

        if ($city) {
            $query->where('city', $city);
        }

        $districts = $query->get();
        $total = $districts->count();

        $this->info("Found {$total} unique city-district combinations to process");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $processed = 0;
        $errors = 0;

        foreach ($districts as $districtData) {
            try {
                $statisticsService->updateDistrictStatistics(
                    $districtData->city,
                    $districtData->district
                );
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nError processing {$districtData->city} - {$districtData->district}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info('Statistics population completed!');
        $this->info("Processed: {$processed}");
        $this->info("Errors: {$errors}");

        $districtCount = DB::table('district_statistics')->count();
        $cityCount = DB::table('city_statistics')->count();

        $this->info("District statistics records: {$districtCount}");
        $this->info("City statistics records: {$cityCount}");
    }
}
