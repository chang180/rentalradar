<?php

namespace App\Listeners;

use App\Events\PropertyCreated;
use App\Jobs\UpdateDistrictStatisticsJob;

class UpdateStatisticsOnPropertyCreated
{
    public function handle(PropertyCreated $event): void
    {
        $property = $event->property;

        UpdateDistrictStatisticsJob::dispatch($property->city, $property->district);
    }
}
