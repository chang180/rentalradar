<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class MapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_fetch_rental_data_for_map_bounds()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/rentals', [
                'bounds' => [
                    'north' => 25.1,
                    'south' => 24.9,
                    'east' => 121.6,
                    'west' => 121.4
                ],
                'filters' => [
                    'price_min' => 10000,
                    'price_max' => 50000
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rentals' => [
                        '*' => [
                            'id',
                            'title',
                            'price',
                            'area',
                            'location' => [
                                'lat',
                                'lng',
                                'address'
                            ],
                            'price_prediction' => [
                                'value',
                                'confidence',
                                'range' => [
                                    'min',
                                    'max'
                                ],
                                'model_version'
                            ]
                        ]
                    ],
                    'statistics' => [
                        'count',
                        'districts',
                        'average_predicted_price',
                        'average_confidence',
                        'confidence_distribution',
                        'confidence_percentiles'
                    ]
                ],
                'meta' => [
                    'performance' => [
                        'name',
                        'response_time',
                        'memory_usage',
                        'checkpoints',
                        'models',
                        'warnings',
                        'query_count'
                    ],
                    'models' => [
                        'price_prediction' => [
                            'version',
                            'average_confidence'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_fetch_heatmap_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/heatmap', [
                'bounds' => [
                    'north' => 25.1,
                    'south' => 24.9,
                    'east' => 121.6,
                    'west' => 121.4
                ],
                'type' => 'price_density'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'heatmap_points' => [
                        '*' => [
                            'lat',
                            'lng',
                            'weight'
                        ]
                    ],
                    'count',
                    'color_scale'
                ],
                'meta' => [
                    'performance' => [
                        'name',
                        'response_time',
                        'memory_usage',
                        'checkpoints',
                        'models',
                        'warnings',
                        'query_count'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_perform_ai_analysis()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/analyze', [
                'type' => 'price_prediction',
                'data' => [
                    'location' => [
                        'lat' => 25.0330,
                        'lng' => 121.5654
                    ],
                    'features' => [
                        'area' => 25,
                        'room_type' => '1房1廳'
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'predictions' => [
                        'items' => [
                            '*' => [
                                'price',
                                'confidence',
                                'range' => [
                                    'min',
                                    'max'
                                ],
                                'model_version',
                                'breakdown',
                                'explanations'
                            ]
                        ],
                        'summary' => [
                            'count',
                            'average_price',
                            'median_price',
                            'price_std_dev',
                            'average_confidence',
                            'confidence_distribution',
                            'confidence_percentiles',
                            'min_price',
                            'max_price'
                        ]
                    ],
                    'model_info',
                    'performance_metrics',
                    'alerts'
                ]
            ]);
    }

    /** @test */
    public function it_can_fetch_clustering_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/clusters', [
                'bounds' => [
                    'north' => 25.1,
                    'south' => 24.9,
                    'east' => 121.6,
                    'west' => 121.4
                ],
                'zoom_level' => 12
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'clusters' => [
                        '*' => [
                            'id',
                            'center' => [
                                'lat',
                                'lng'
                            ],
                            'count',
                            'bounds'
                        ]
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
                        'max_price'
                    ]
                ],
                'meta' => [
                    'performance' => [
                        'name',
                        'response_time',
                        'memory_usage',
                        'checkpoints',
                        'models',
                        'warnings',
                        'query_count'
                    ],
                    'models' => [
                        'price_prediction' => [
                            'version',
                            'average_confidence'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_map_bounds_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/rentals', [
                'bounds' => [
                    'north' => 'invalid',
                    'south' => 24.9,
                    'east' => 121.6,
                    'west' => 121.4
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details'
                ]
            ]);
    }

    /** @test */
    public function it_handles_authentication_errors()
    {
        $response = $this->getJson('/api/map/rentals', [
            'bounds' => [
                'north' => 25.1,
                'south' => 24.9,
                'east' => 121.6,
                'west' => 121.4
            ]
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message'
                ]
            ]);
    }

    /** @test */
    public function it_implements_rate_limiting()
    {
        // 模擬大量請求
        for ($i = 0; $i < 100; $i++) {
            $response = $this->actingAs($this->user)
                ->getJson('/api/map/rentals', [
                    'bounds' => [
                        'north' => 25.1,
                        'south' => 24.9,
                        'east' => 121.6,
                        'west' => 121.4
                    ]
                ]);
        }

        $response->assertStatus(429)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message'
                ]
            ]);
    }

    /** @test */
    public function it_returns_performance_metrics()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/map/rentals', [
                'bounds' => [
                    'north' => 25.1,
                    'south' => 24.9,
                    'east' => 121.6,
                    'west' => 121.4
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rentals',
                    'statistics'
                ],
                'meta' => [
                    'performance' => [
                        'name',
                        'response_time',
                        'memory_usage',
                        'checkpoints',
                        'models',
                        'warnings',
                        'query_count'
                    ],
                    'models' => [
                        'price_prediction' => [
                            'version',
                            'average_confidence'
                        ]
                    ]
                ]
            ]);

        // 檢查效能指標
        $data = $response->json();
        $this->assertLessThan(1000, $data['meta']['performance']['response_time']);
        $this->assertLessThan(128, $data['meta']['performance']['memory_usage']);
        $this->assertGreaterThanOrEqual(1, $data['meta']['performance']['query_count']);
        $this->assertArrayHasKey('price_prediction', $data['meta']['models']);
        $this->assertArrayHasKey('confidence_distribution', $data['data']['statistics']);
        $this->assertArrayHasKey('confidence_percentiles', $data['data']['statistics']);
        $this->assertArrayHasKey('warnings', $data['meta']['performance']);
    }
}
