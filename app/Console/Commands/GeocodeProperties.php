<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeProperties extends Command
{
    protected $signature = 'properties:geocode {--limit=10 : Number of properties to geocode} {--force : Force re-geocode already geocoded properties}';

    protected $description = 'Geocode property addresses to get latitude and longitude coordinates';

    public function __construct(private GeocodingService $geocodingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $query = Property::query();

        if (!$force) {
            $query->where('is_geocoded', false);
        }

        $properties = $query->limit($limit)->get();

        if ($properties->isEmpty()) {
            $this->info('No properties found to geocode.');
            return self::SUCCESS;
        }

        $this->info("Starting geocoding for {$properties->count()} properties...");

        $progressBar = $this->output->createProgressBar($properties->count());
        $progressBar->start();

        $successful = 0;
        $failed = 0;

        foreach ($properties as $property) {
            $result = $this->geocodingService->geocodeProperty($property);

            if ($result) {
                $successful++;
                $this->line("\n✅ Geocoded: {$property->district}{$property->road}");
            } else {
                $failed++;
                $this->line("\n❌ Failed: {$property->district}{$property->road}");
            }

            $progressBar->advance();
            sleep(1);
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info("Geocoding completed!");
        $this->info("✅ Successful: {$successful}");
        $this->info("❌ Failed: {$failed}");

        return self::SUCCESS;
    }
}
