<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Support\AdvancedPricePredictor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedPricePredictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 建立測試資料
        $this->seedTestData();
        $this->user = User::factory()->create();
    }

    private function seedTestData(): void
    {
        // 建立測試用的租賃物件
        $properties = [
            [
                'district' => '大安區',
                'village' => '測試里',
                'road' => '信義路四段123號',
                'land_section' => '測試段',
                'land_subsection' => '測試小段',
                'land_number' => 123,
                'building_type' => '住宅大樓',
                'total_floor_area' => 25.5,
                'main_use' => '住家用',
                'main_building_materials' => '鋼筋混凝土造',
                'construction_completion_year' => 2020,
                'total_floors' => 10,
                'compartment_pattern' => '2房1廳1衛',
                'has_management_organization' => true,
                'rent_per_month' => 35000,
                'total_rent' => 35000,
                'rent_date' => '2024-01-15',
                'rental_period' => '12個月',
                'is_geocoded' => true,
                'latitude' => 25.0330,
                'longitude' => 121.5650,
                'full_address' => '台北市大安區信義路四段123號',
                'data_source' => 'test',
                'is_processed' => true,
                'processing_notes' => json_encode(['source' => 'test']),
            ],
            [
                'district' => '信義區',
                'village' => '測試里',
                'road' => '松仁路456號',
                'land_section' => '測試段',
                'land_subsection' => '測試小段',
                'land_number' => 456,
                'building_type' => '華廈',
                'total_floor_area' => 30.0,
                'main_use' => '住家用',
                'main_building_materials' => '鋼筋混凝土造',
                'construction_completion_year' => 2018,
                'total_floors' => 8,
                'compartment_pattern' => '3房2廳2衛',
                'has_management_organization' => true,
                'rent_per_month' => 45000,
                'total_rent' => 45000,
                'rent_date' => '2024-02-01',
                'rental_period' => '12個月',
                'is_geocoded' => true,
                'latitude' => 25.0280,
                'longitude' => 121.5700,
                'full_address' => '台北市信義區松仁路456號',
                'data_source' => 'test',
                'is_processed' => true,
                'processing_notes' => json_encode(['source' => 'test']),
            ],
            [
                'district' => '中山區',
                'village' => '測試里',
                'road' => '南京東路二段789號',
                'land_section' => '測試段',
                'land_subsection' => '測試小段',
                'land_number' => 789,
                'building_type' => '公寓',
                'total_floor_area' => 20.0,
                'main_use' => '住家用',
                'main_building_materials' => '鋼筋混凝土造',
                'construction_completion_year' => 2015,
                'total_floors' => 5,
                'compartment_pattern' => '1房1廳1衛',
                'has_management_organization' => false,
                'rent_per_month' => 25000,
                'total_rent' => 25000,
                'rent_date' => '2024-01-20',
                'rental_period' => '6個月',
                'is_geocoded' => true,
                'latitude' => 25.0520,
                'longitude' => 121.5300,
                'full_address' => '台北市中山區南京東路二段789號',
                'data_source' => 'test',
                'is_processed' => true,
                'processing_notes' => json_encode(['source' => 'test']),
            ],
        ];

        foreach ($properties as $propertyData) {
            Property::create($propertyData);
        }
    }

    public function test_advanced_price_predictor_works(): void
    {
        $predictor = new AdvancedPricePredictor();
        $property = Property::first();

        $prediction = $predictor->predict($property->toArray());

        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('price', $prediction);
        $this->assertArrayHasKey('confidence', $prediction);
        $this->assertArrayHasKey('range', $prediction);
        $this->assertArrayHasKey('breakdown', $prediction);
        $this->assertArrayHasKey('explanations', $prediction);
        $this->assertArrayHasKey('model_version', $prediction);

        $this->assertIsNumeric($prediction['price']);
        $this->assertIsNumeric($prediction['confidence']);
        $this->assertArrayHasKey('min', $prediction['range']);
        $this->assertArrayHasKey('max', $prediction['range']);
        $this->assertIsArray($prediction['breakdown']);
        $this->assertIsArray($prediction['explanations']);
    }

    public function test_batch_price_prediction_works(): void
    {
        $predictor = new AdvancedPricePredictor();
        $properties = Property::all()->toArray();

        $predictions = $predictor->predictCollection($properties);
        $summary = $predictor->summarize($predictions);

        $this->assertIsArray($predictions);
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('count', $summary);
        $this->assertArrayHasKey('average_price', $summary);
        $this->assertArrayHasKey('median_price', $summary);
        $this->assertArrayHasKey('price_std_dev', $summary);
        $this->assertArrayHasKey('average_confidence', $summary);
        $this->assertArrayHasKey('confidence_distribution', $summary);
        $this->assertArrayHasKey('confidence_percentiles', $summary);
        $this->assertArrayHasKey('min_price', $summary);
        $this->assertArrayHasKey('max_price', $summary);

        $this->assertArrayHasKey('p50', $summary['confidence_percentiles']);
        $this->assertArrayHasKey('p75', $summary['confidence_percentiles']);
        $this->assertArrayHasKey('p90', $summary['confidence_percentiles']);
    }

    public function test_rentals_api_returns_price_predictions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/rentals', [
                'bounds' => [
                'north' => 25.1,
                'south' => 25.0,
                'east' => 121.6,
                'west' => 121.5,
            ],
            'zoom' => 12,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rentals' => [
                        '*' => [
                            'id',
                            'title',
                            'price',
                            'area',
                            'location' => ['lat', 'lng', 'address'],
                            'price_prediction' => [
                                'value',
                                'confidence',
                                'range' => ['min', 'max'],
                                'model_version',
                            ],
                        ],
                    ],
                    'statistics' => [
                        'count',
                        'districts',
                        'average_predicted_price',
                        'average_confidence',
                        'confidence_distribution',
                        'confidence_percentiles',
                    ],
                ],
                'meta' => [
                    'performance' => ['name', 'response_time', 'memory_usage', 'checkpoints', 'models', 'warnings', 'query_count'],
                    'models' => [
                        'price_prediction' => ['version', 'average_confidence'],
                    ],
                ],
            ]);

        $statistics = $response->json('data.statistics');
        $this->assertArrayHasKey('confidence_percentiles', $statistics);
        $this->assertArrayHasKey('p50', $statistics['confidence_percentiles']);
        $this->assertArrayHasKey('p75', $statistics['confidence_percentiles']);
        $this->assertArrayHasKey('p90', $statistics['confidence_percentiles']);
    }

    public function test_clusters_api_returns_price_predictions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/clusters', [
                'bounds' => [
                    'north' => 25.1,
                    'south' => 25.0,
                    'east' => 121.6,
                    'west' => 121.5,
                ],
                'zoom' => 12,
            ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'clusters' => [
                        '*' => [
                            'id',
                            'center',
                            'count',
                            'bounds',
                            'radius_km',
                            'density',
                        ],
                    ],
                    'algorithm_info',
                    'price_summary' => [
                        'count',
                        'average_price',
                        'median_price',
                        'price_std_dev',
                        'average_confidence',
                        'confidence_distribution',
                        'confidence_percentiles',
                        'min_price',
                        'max_price',
                    ],
                ],
                'meta' => [
                    'performance' => ['name', 'response_time', 'memory_usage', 'checkpoints', 'models', 'warnings', 'query_count'],
                    'models' => [
                        'price_prediction' => ['version', 'average_confidence'],
                    ],
                ],
            ]);

        $summary = $response->json('data.price_summary');
        $this->assertArrayHasKey('confidence_percentiles', $summary);
        $this->assertArrayHasKey('p50', $summary['confidence_percentiles']);
        $this->assertArrayHasKey('p75', $summary['confidence_percentiles']);
        $this->assertArrayHasKey('p90', $summary['confidence_percentiles']);
    }

    public function test_optimized_data_api_returns_price_predictions(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/optimized-data', [
            'bounds' => [
                'north' => 25.1,
                'south' => 25.0,
                'east' => 121.6,
                'west' => 121.5,
            ],
            'zoom' => 12,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'type',
                    'count',
                    'properties' => [
                        '*' => [
                            'id',
                            'position',
                            'info',
                            'price_prediction',
                        ],
                    ],
                    'price_summary' => [
                        'count',
                        'average_price',
                        'median_price',
                        'price_std_dev',
                        'average_confidence',
                        'confidence_distribution',
                        'confidence_percentiles',
                        'min_price',
                        'max_price',
                    ],
                ],
                'meta' => [
                    'performance' => ['name', 'response_time', 'memory_usage', 'checkpoints', 'models', 'warnings', 'query_count'],
                    'models' => [
                        'price_prediction' => ['version', 'average_confidence'],
                    ],
                ],
            ]);

        $summary = $response->json('data.price_summary');
        $this->assertArrayHasKey('confidence_percentiles', $summary);
        $this->assertArrayHasKey('average_confidence', $summary);
        $this->assertArrayHasKey('confidence_distribution', $summary);
    }

    public function test_optimized_data_cluster_payload_contains_performance_metrics(): void
    {
        Property::factory()->count(150)->create([
            'is_geocoded' => true,
            'latitude' => 25.05,
            'longitude' => 121.55,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/map/optimized-data', [
                'bounds' => [
                    'north' => 25.2,
                    'south' => 25.0,
                    'east' => 121.7,
                    'west' => 121.4,
                ],
                'zoom' => 12,
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'clusters')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'type',
                    'clusters' => [
                        '*' => [
                            'id',
                            'center' => ['lat', 'lng'],
                            'count',
                            'bounds' => ['north', 'south', 'east', 'west'],
                            'radius_km',
                            'density',
                        ],
                    ],
                    'optimization_info' => [
                        'original_count',
                        'cluster_count',
                        'reduction_ratio',
                    ],
                    'price_summary' => [
                        'count',
                        'average_price',
                        'median_price',
                        'price_std_dev',
                        'average_confidence',
                        'confidence_distribution',
                        'confidence_percentiles',
                        'min_price',
                        'max_price',
                    ],
                ],
                'meta' => [
                    'performance' => [
                        'name',
                        'response_time',
                        'memory_usage',
                        'checkpoints',
                        'query_count',
                    ],
                    'models' => [
                        'price_prediction' => [
                            'version',
                            'average_confidence',
                        ],
                    ],
                ],
            ]);

        $performance = $response->json('meta.performance');
        $this->assertIsArray($performance['checkpoints']);
        $this->assertGreaterThan(0, $performance['response_time']);
        $this->assertGreaterThanOrEqual(0, $performance['memory_usage']);
    }

    public function test_confidence_percentiles_are_calculated_correctly(): void
    {
        $predictor = new AdvancedPricePredictor();
        
        // 建立測試用的信心度陣列
        $confidences = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0];
        
        // 使用反射來測試私有方法
        $reflection = new \ReflectionClass($predictor);
        $method = $reflection->getMethod('confidencePercentiles');
        $method->setAccessible(true);
        
        $result = $method->invoke($predictor, $confidences);
        
        $this->assertArrayHasKey('p50', $result);
        $this->assertArrayHasKey('p75', $result);
        $this->assertArrayHasKey('p90', $result);
        
        // 驗證百分位數值
        $this->assertEquals(0.55, $result['p50']);
        $this->assertEquals(0.775, $result['p75']);
        $this->assertEquals(0.91, $result['p90']);
    }

    public function test_empty_confidence_array_handled_gracefully(): void
    {
        $predictor = new AdvancedPricePredictor();
        
        // 使用反射來測試私有方法
        $reflection = new \ReflectionClass($predictor);
        $method = $reflection->getMethod('confidencePercentiles');
        $method->setAccessible(true);
        
        $result = $method->invoke($predictor, []);
        
        $this->assertArrayHasKey('p50', $result);
        $this->assertArrayHasKey('p75', $result);
        $this->assertArrayHasKey('p90', $result);
        
        $this->assertEquals(0.0, $result['p50']);
        $this->assertEquals(0.0, $result['p75']);
        $this->assertEquals(0.0, $result['p90']);
    }
}
