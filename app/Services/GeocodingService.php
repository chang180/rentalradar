<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private string $baseUrl = 'https://nominatim.openstreetmap.org/search';

    public function geocodeAddress(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'RentalRadar/1.0 (taiwan.rental.radar@gmail.com)',
            ])
            ->timeout(10)
            ->get($this->baseUrl, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'tw',
                'addressdetails' => 1,
            ]);

            if ($response->successful() && $response->json()) {
                $data = $response->json();
                if (!empty($data)) {
                    $result = $data[0];
                    return [
                        'latitude' => (float) $result['lat'],
                        'longitude' => (float) $result['lon'],
                        'display_name' => $result['display_name'] ?? null,
                        'accuracy' => $this->calculateAccuracy($result),
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Geocoding failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function batchGeocode(array $addresses, int $delay = 1): array
    {
        $results = [];

        foreach ($addresses as $index => $address) {
            $result = $this->geocodeAddress($address);
            $results[$index] = $result;

            if ($delay > 0 && $index < count($addresses) - 1) {
                sleep($delay);
            }
        }

        return $results;
    }

    public function geocodeProperty(\App\Models\Property $property): bool
    {
        if ($property->is_geocoded) {
            return true;
        }

        $address = $this->buildFullAddress($property);
        $result = $this->geocodeAddress($address);

        if ($result) {
            $property->update([
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude'],
                'full_address' => $result['display_name'] ?? $address,
                'is_geocoded' => true,
                'processing_notes' => array_merge(
                    $property->processing_notes ?? [],
                    ['geocoded_at' => now()->toISOString()]
                ),
            ]);

            return true;
        }

        return false;
    }

    private function buildFullAddress(\App\Models\Property $property): string
    {
        // 移除路段號碼以提高地理編碼成功率
        $road = preg_replace('/\d+段$/', '', $property->road);
        $address = '台北市' . $property->district . $road;

        return $address;
    }

    private function calculateAccuracy(array $result): string
    {
        $type = $result['type'] ?? 'unknown';
        $class = $result['class'] ?? 'unknown';

        if ($class === 'building' || $type === 'house') {
            return 'high';
        } elseif ($class === 'highway' || $type === 'road') {
            return 'medium';
        } elseif ($class === 'place') {
            return 'low';
        }

        return 'unknown';
    }

    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'RentalRadar/1.0 (taiwan.rental.radar@gmail.com)',
            ])
            ->timeout(10)
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

            if ($response->successful() && $response->json()) {
                $data = $response->json();
                return [
                    'display_name' => $data['display_name'] ?? null,
                    'address' => $data['address'] ?? [],
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Reverse geocoding failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
